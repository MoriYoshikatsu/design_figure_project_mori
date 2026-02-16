<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\SalesRoutePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AdminAccountController extends Controller
{
    public function __construct(
        private readonly SalesRoutePermissionService $salesRoutePermissionService
    ) {
    }

    public function index(Request $request)
    {
        $q = trim((string)$request->input('q', ''));

        $roleList = DB::table('account_user as au')
            ->selectRaw("string_agg(distinct au.role, ' / ' order by au.role)")
            ->whereColumn('au.account_id', 'a.id');

        $roleSummary = DB::table('account_user as au')
            ->selectRaw("
                'admin:' || count(*) filter (where au.role = 'admin')
                || ' / sales:' || count(*) filter (where au.role = 'sales')
                || ' / customer:' || count(*) filter (where au.role = 'customer')
            ")
            ->whereColumn('au.account_id', 'a.id');

        $memberSummary = DB::table('account_user as au')
            ->join('users as u', 'u.id', '=', 'au.user_id')
            ->selectRaw("string_agg(u.name || ' (' || au.role || ')', ', ' order by u.id)")
            ->whereColumn('au.account_id', 'a.id');

        $fallbackUserName = DB::table('account_user as au')
            ->join('users as u', 'u.id', '=', 'au.user_id')
            ->whereColumn('au.account_id', 'a.id')
            ->orderByRaw("
                case au.role
                    when 'customer' then 1
                    when 'admin' then 2
                    when 'sales' then 3
                    else 9
                end
            ")
            ->orderBy('au.user_id')
            ->select('u.name')
            ->limit(1);

        $query = DB::table('accounts as a')
            ->select('a.*')
            ->selectSub($roleList, 'role_list')
            ->selectSub($roleSummary, 'role_summary')
            ->selectSub($memberSummary, 'member_summary')
            ->selectSub($fallbackUserName, 'fallback_user_name');
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('a.internal_name', 'ilike', "%{$q}%")
                    ->orWhere('a.assignee_name', 'ilike', "%{$q}%")
                    ->orWhere('a.memo', 'ilike', "%{$q}%")
                    ->orWhereExists(function ($sq) use ($q) {
                        $sq->selectRaw('1')
                            ->from('account_user as au')
                            ->join('users as u', 'u.id', '=', 'au.user_id')
                            ->whereColumn('au.account_id', 'a.id')
                            ->where('u.name', 'ilike', "%{$q}%");
                    });
            });
        }

        $accounts = $query->orderBy('a.id', 'desc')->limit(200)->get();
        $accountIds = $accounts->pluck('id')->map(fn ($v) => (int)$v)->all();

        $permissionRows = empty($accountIds)
            ? collect()
            : DB::table('account_sales_route_permissions')
                ->whereIn('account_id', $accountIds)
                ->where('active', true)
                ->orderBy('id')
                ->get(['account_id', 'http_method', 'uri_pattern']);

        $permissionMap = [];
        foreach ($permissionRows as $row) {
            $aid = (int)$row->account_id;
            if (!isset($permissionMap[$aid])) {
                $permissionMap[$aid] = [];
            }
            $permissionMap[$aid][] = sprintf('%s %s', (string)$row->http_method, (string)$row->uri_pattern);
        }

        $routeCatalog = $this->salesRoutePermissionService->routeCatalog();
        foreach ($accounts as $account) {
            $aid = (int)$account->id;
            $policyMode = (string)($account->sales_route_policy_mode ?? 'legacy_allow_all');
            $previews = $permissionMap[$aid] ?? [];
            if ($policyMode === 'legacy_allow_all') {
                $account->route_access_summary = 'Sales: 全ルート許可（管理・運用画面の全操作）';
            } else {
                $account->route_access_summary = $this->buildRouteAccessSummary($previews, $routeCatalog);
            }
        }

        return view('admin.accounts.index', [
            'accounts' => $accounts,
            'filters' => ['q' => $q],
        ]);
    }

    public function edit(int $id)
    {
        $account = DB::table('accounts')->where('id', $id)->first();
        if (!$account) abort(404);

        $members = DB::table('account_user as au')
            ->join('users as u', 'u.id', '=', 'au.user_id')
            ->where('au.account_id', $id)
            ->select(
                'au.user_id',
                'au.role',
                'au.memo',
                'au.created_at as assigned_at',
                'u.name as user_name',
                'u.email as user_email'
            )
            ->orderByRaw("case au.role when 'admin' then 1 when 'sales' then 2 else 3 end")
            ->orderBy('u.id')
            ->get();

        $roleCounts = [
            'admin' => $members->where('role', 'admin')->count(),
            'sales' => $members->where('role', 'sales')->count(),
            'customer' => $members->where('role', 'customer')->count(),
        ];

        return view('admin.accounts.edit', [
            'account' => $account,
            'members' => $members,
            'roleCounts' => $roleCounts,
        ]);
    }

    public function permissions(int $id)
    {
        $account = DB::table('accounts')->where('id', $id)->first();
        if (!$account) abort(404);

        $salesRouteViewData = $this->buildSalesRouteViewData($id);

        return view('admin.accounts.permissions', array_merge([
            'account' => $account,
        ], $salesRouteViewData));
    }

    public function update(Request $request, int $id)
    {
        $account = DB::table('accounts')->where('id', $id)->first();
        if (!$account) abort(404);

        $data = $request->validate([
            'account_type' => 'required|in:B2B,B2C',
            'internal_name' => 'nullable|string|max:255',
            'memo' => 'nullable|string|max:5000',
            'assignee_name' => 'nullable|string|max:255',
        ]);

        $internal = trim((string)($data['internal_name'] ?? ''));
        if ($internal === '') $internal = null;
        $memo = trim((string)($data['memo'] ?? ''));
        if ($memo === '') $memo = null;
        $assigneeName = trim((string)($data['assignee_name'] ?? ''));
        if ($assigneeName === '') $assigneeName = null;

        $before = (array)$account;

        DB::table('accounts')->where('id', $id)->update([
            'account_type' => $data['account_type'],
            'internal_name' => $internal,
            'memo' => $memo,
            'assignee_name' => $assigneeName,
            'updated_at' => now(),
        ]);

        $after = (array)DB::table('accounts')->where('id', $id)->first();
        app(AuditLogger::class)->log((int)auth()->id(), 'ACCOUNT_UPDATED', 'account', $id, $before, $after);

        return redirect()->route('admin.accounts.edit', $id)->with('status', 'アカウントを更新しました');
    }

    public function updateMemberMemo(Request $request, int $id, int $userId)
    {
        $account = DB::table('accounts')->where('id', $id)->first();
        if (!$account) abort(404);

        $exists = DB::table('account_user')
            ->where('account_id', $id)
            ->where('user_id', $userId)
            ->exists();
        if (!$exists) abort(404);

        $data = $request->validate([
            'memo' => 'nullable|string|max:5000',
        ]);
        $memo = trim((string)($data['memo'] ?? ''));
        if ($memo === '') $memo = null;

        $before = (array)DB::table('account_user')
            ->where('account_id', $id)
            ->where('user_id', $userId)
            ->first();

        DB::table('account_user')
            ->where('account_id', $id)
            ->where('user_id', $userId)
            ->update([
                'memo' => $memo,
                'updated_at' => now(),
            ]);

        $after = (array)DB::table('account_user')
            ->where('account_id', $id)
            ->where('user_id', $userId)
            ->first();

        app(AuditLogger::class)->log((int)auth()->id(), 'ACCOUNT_USER_MEMO_UPDATED', 'account_user', null, $before, $after);

        return redirect()->route('admin.accounts.edit', $id)->with('status', '権限設定メモを更新しました');
    }

    public function updateSalesRoutePolicy(Request $request, int $id)
    {
        $account = DB::table('accounts')->where('id', $id)->first();
        if (!$account) abort(404);

        $data = $request->validate([
            'sales_route_policy_mode' => 'required|in:legacy_allow_all,strict_allowlist',
        ]);

        $before = (array)$account;

        DB::table('accounts')->where('id', $id)->update([
            'sales_route_policy_mode' => $data['sales_route_policy_mode'],
            'updated_at' => now(),
        ]);

        $after = (array)DB::table('accounts')->where('id', $id)->first();
        app(AuditLogger::class)->log(
            (int)auth()->id(),
            'ACCOUNT_SALES_ROUTE_POLICY_UPDATED',
            'account',
            $id,
            $before,
            $after
        );

        return redirect()->route('admin.accounts.edit', $id)->with('status', 'Salesルートポリシーを更新しました');
    }

    public function storeSalesRoutePermission(Request $request, int $id)
    {
        $account = DB::table('accounts')->where('id', $id)->first();
        if (!$account) abort(404);

        if ($request->boolean('catalog_sync')) {
            return $this->syncCatalogPermissions($request, $id);
        }

        $data = $request->validate([
            'http_method' => 'required|string|max:10',
            'uri_pattern' => 'required|string|max:255',
            'source' => 'nullable|in:checkbox,manual',
            'memo' => 'nullable|string|max:5000',
        ]);

        $method = $this->salesRoutePermissionService->normalizeMethod((string)$data['http_method']);
        $pattern = $this->salesRoutePermissionService->normalizePattern((string)$data['uri_pattern']);
        if (
            !in_array($method, $this->salesRoutePermissionService->allowedMethods(), true)
            || !$this->isValidUriPattern($pattern)
        ) {
            return redirect()
                ->route('admin.accounts.edit', $id)
                ->withErrors(['uri_pattern' => 'URIパターンかHTTPメソッドが不正です。'])
                ->withInput();
        }

        $memo = trim((string)($data['memo'] ?? ''));
        if ($memo === '') {
            $memo = null;
        }
        $source = (string)($data['source'] ?? 'manual');
        $userId = (int)auth()->id();

        $existing = DB::table('account_sales_route_permissions')
            ->where('account_id', $id)
            ->where('http_method', $method)
            ->where('uri_pattern', $pattern)
            ->first();

        if ($existing) {
            $before = (array)$existing;
            DB::table('account_sales_route_permissions')
                ->where('id', (int)$existing->id)
                ->update([
                    'source' => $source,
                    'active' => true,
                    'memo' => $memo,
                    'updated_by' => $userId > 0 ? $userId : null,
                    'updated_at' => now(),
                ]);
            $after = (array)DB::table('account_sales_route_permissions')->where('id', (int)$existing->id)->first();
            app(AuditLogger::class)->log(
                $userId,
                'ACCOUNT_SALES_ROUTE_PERMISSION_UPDATED',
                'account_sales_route_permission',
                (int)$existing->id,
                $before,
                $after
            );
            return redirect()->route('admin.accounts.edit', $id)->with('status', 'Salesルート許可を更新しました');
        }

        $newId = (int)DB::table('account_sales_route_permissions')->insertGetId([
            'account_id' => $id,
            'http_method' => $method,
            'uri_pattern' => $pattern,
            'source' => $source,
            'active' => true,
            'memo' => $memo,
            'created_by' => $userId > 0 ? $userId : null,
            'updated_by' => $userId > 0 ? $userId : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $after = (array)DB::table('account_sales_route_permissions')->where('id', $newId)->first();
        app(AuditLogger::class)->log(
            $userId,
            'ACCOUNT_SALES_ROUTE_PERMISSION_CREATED',
            'account_sales_route_permission',
            $newId,
            null,
            $after
        );

        return redirect()->route('admin.accounts.edit', $id)->with('status', 'Salesルート許可を追加しました');
    }

    public function updateSalesRoutePermission(Request $request, int $id, int $permId)
    {
        $perm = DB::table('account_sales_route_permissions')
            ->where('account_id', $id)
            ->where('id', $permId)
            ->first();
        if (!$perm) abort(404);

        $data = $request->validate([
            'http_method' => 'required|string|max:10',
            'uri_pattern' => 'required|string|max:255',
            'source' => 'required|in:checkbox,manual',
            'active' => 'nullable|boolean',
            'memo' => 'nullable|string|max:5000',
        ]);

        $method = $this->salesRoutePermissionService->normalizeMethod((string)$data['http_method']);
        $pattern = $this->salesRoutePermissionService->normalizePattern((string)$data['uri_pattern']);
        if (
            !in_array($method, $this->salesRoutePermissionService->allowedMethods(), true)
            || !$this->isValidUriPattern($pattern)
        ) {
            return redirect()
                ->route('admin.accounts.edit', $id)
                ->withErrors(['uri_pattern' => 'URIパターンかHTTPメソッドが不正です。'])
                ->withInput();
        }

        $memo = trim((string)($data['memo'] ?? ''));
        if ($memo === '') {
            $memo = null;
        }
        $active = filter_var($data['active'] ?? false, FILTER_VALIDATE_BOOL);
        $userId = (int)auth()->id();
        $before = (array)$perm;

        DB::table('account_sales_route_permissions')
            ->where('id', $permId)
            ->update([
                'http_method' => $method,
                'uri_pattern' => $pattern,
                'source' => $data['source'],
                'active' => $active,
                'memo' => $memo,
                'updated_by' => $userId > 0 ? $userId : null,
                'updated_at' => now(),
            ]);

        $after = (array)DB::table('account_sales_route_permissions')->where('id', $permId)->first();
        app(AuditLogger::class)->log(
            $userId,
            'ACCOUNT_SALES_ROUTE_PERMISSION_UPDATED',
            'account_sales_route_permission',
            $permId,
            $before,
            $after
        );

        return redirect()->route('admin.accounts.edit', $id)->with('status', 'Salesルート許可を更新しました');
    }

    public function destroySalesRoutePermission(int $id, int $permId)
    {
        $perm = DB::table('account_sales_route_permissions')
            ->where('account_id', $id)
            ->where('id', $permId)
            ->first();
        if (!$perm) abort(404);

        $before = (array)$perm;
        DB::table('account_sales_route_permissions')->where('id', $permId)->delete();

        app(AuditLogger::class)->log(
            (int)auth()->id(),
            'ACCOUNT_SALES_ROUTE_PERMISSION_DELETED',
            'account_sales_route_permission',
            $permId,
            $before,
            null
        );

        return redirect()->route('admin.accounts.edit', $id)->with('status', 'Salesルート許可を削除しました');
    }

    private function syncCatalogPermissions(Request $request, int $accountId)
    {
        $data = $request->validate([
            'catalog_permissions' => 'array',
            'catalog_permissions.*' => 'string|max:400',
        ]);

        $selected = collect($data['catalog_permissions'] ?? [])
            ->map(function (string $line): ?array {
                $parts = explode(' ', $line, 2);
                if (count($parts) !== 2) {
                    return null;
                }
                $method = $this->salesRoutePermissionService->normalizeMethod($parts[0]);
                $pattern = $this->salesRoutePermissionService->normalizePattern($parts[1]);
                if (
                    !in_array($method, $this->salesRoutePermissionService->allowedMethods(), true)
                    || !$this->isValidUriPattern($pattern)
                ) {
                    return null;
                }
                return ['method' => $method, 'pattern' => $pattern];
            })
            ->filter()
            ->values();

        /** @var Collection<int, object> $existing */
        $existing = DB::table('account_sales_route_permissions')
            ->where('account_id', $accountId)
            ->where('source', 'checkbox')
            ->get();

        /** @var Collection<int, object> $existingAll */
        $existingAll = DB::table('account_sales_route_permissions')
            ->where('account_id', $accountId)
            ->get();

        $before = $existing->map(fn (object $row): array => (array)$row)->all();
        $byKey = [];
        foreach ($existingAll as $row) {
            $key = strtoupper((string)$row->http_method) . ' ' . (string)$row->uri_pattern;
            $byKey[$key] = $row;
        }

        $selectedKeys = [];
        $userId = (int)auth()->id();
        foreach ($selected as $item) {
            $key = $item['method'] . ' ' . $item['pattern'];
            $selectedKeys[$key] = true;
            if (isset($byKey[$key])) {
                $beforeRow = (array)$byKey[$key];
                DB::table('account_sales_route_permissions')
                    ->where('id', (int)$byKey[$key]->id)
                    ->update([
                        'active' => true,
                        'updated_by' => $userId > 0 ? $userId : null,
                        'updated_at' => now(),
                    ]);
                $afterRow = (array)DB::table('account_sales_route_permissions')
                    ->where('id', (int)$byKey[$key]->id)
                    ->first();
                if ($beforeRow !== $afterRow) {
                    app(AuditLogger::class)->log(
                        $userId,
                        'ACCOUNT_SALES_ROUTE_PERMISSION_UPDATED',
                        'account_sales_route_permission',
                        (int)$byKey[$key]->id,
                        $beforeRow,
                        $afterRow
                    );
                }
                continue;
            }

            $newId = (int)DB::table('account_sales_route_permissions')->insertGetId([
                'account_id' => $accountId,
                'http_method' => $item['method'],
                'uri_pattern' => $item['pattern'],
                'source' => 'checkbox',
                'active' => true,
                'memo' => null,
                'created_by' => $userId > 0 ? $userId : null,
                'updated_by' => $userId > 0 ? $userId : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $afterRow = (array)DB::table('account_sales_route_permissions')->where('id', $newId)->first();
            app(AuditLogger::class)->log(
                $userId,
                'ACCOUNT_SALES_ROUTE_PERMISSION_CREATED',
                'account_sales_route_permission',
                $newId,
                null,
                $afterRow
            );
        }

        foreach ($existing as $row) {
            $key = strtoupper((string)$row->http_method) . ' ' . (string)$row->uri_pattern;
            if (isset($selectedKeys[$key])) {
                continue;
            }
            $beforeRow = (array)$row;
            DB::table('account_sales_route_permissions')
                ->where('id', (int)$row->id)
                ->update([
                    'active' => false,
                    'updated_by' => $userId > 0 ? $userId : null,
                    'updated_at' => now(),
                ]);
            $afterRow = (array)DB::table('account_sales_route_permissions')
                ->where('id', (int)$row->id)
                ->first();
            if ($beforeRow !== $afterRow) {
                app(AuditLogger::class)->log(
                    $userId,
                    'ACCOUNT_SALES_ROUTE_PERMISSION_UPDATED',
                    'account_sales_route_permission',
                    (int)$row->id,
                    $beforeRow,
                    $afterRow
                );
            }
        }

        $after = DB::table('account_sales_route_permissions')
            ->where('account_id', $accountId)
            ->where('source', 'checkbox')
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): array => (array)$row)
            ->all();

        app(AuditLogger::class)->log(
            $userId,
            'ACCOUNT_SALES_ROUTE_PERMISSION_SYNCED',
            'account',
            $accountId,
            $before,
            $after
        );

        return redirect()->route('admin.accounts.edit', $accountId)->with('status', 'チェックボックス許可を反映しました');
    }

    private function isValidUriPattern(string $pattern): bool
    {
        if (!str_starts_with($pattern, '/')) {
            return false;
        }
        if (preg_match('/\s/', $pattern)) {
            return false;
        }

        return true;
    }

    /**
     * @return array{
     *   salesRouteCatalog: array<int, array{method:string,uri:string,route_name:string,suggested_pattern:string,memo:string}>,
     *   salesRoutePermissions: \Illuminate\Support\Collection<int, object>,
     *   salesRoutePermissionStateMap: array<string, bool>,
     *   salesRouteAllowedMethods: array<int, string>
     * }
     */
    private function buildSalesRouteViewData(int $accountId): array
    {
        $permissions = DB::table('account_sales_route_permissions')
            ->where('account_id', $accountId)
            ->orderByDesc('active')
            ->orderBy('http_method')
            ->orderBy('uri_pattern')
            ->get();

        $permissionStateMap = [];
        foreach ($permissions as $perm) {
            $k = strtoupper((string)$perm->http_method) . ' ' . (string)$perm->uri_pattern;
            $permissionStateMap[$k] = (bool)$perm->active;
        }

        $routeCatalogRaw = $this->salesRoutePermissionService->routeCatalog();
        $routeCatalog = array_map(function (array $row): array {
            $row['memo'] = $this->buildCatalogRouteMemo($row);
            return $row;
        }, $routeCatalogRaw);

        return [
            'salesRouteCatalog' => $routeCatalog,
            'salesRoutePermissions' => $permissions,
            'salesRoutePermissionStateMap' => $permissionStateMap,
            'salesRouteAllowedMethods' => $this->salesRoutePermissionService->allowedMethods(),
        ];
    }

    /**
     * @param array{method:string,uri:string,route_name:string,suggested_pattern:string} $route
     */
    private function buildCatalogRouteMemo(array $route): string
    {
        $routeName = trim((string)($route['route_name'] ?? ''));
        $uri = (string)($route['uri'] ?? '/');
        $method = strtoupper((string)($route['method'] ?? 'GET'));

        [$pageLabel, $actionLabel] = $this->routeNameToPageAction($routeName);
        if ($routeName === '' || $pageLabel === 'その他ページ') {
            $pageLabel = $this->uriToPageLabel($uri);
        }
        if ($routeName === '' || $actionLabel === '接続') {
            $actionLabel = $this->guessActionLabelFromUriAndMethod($uri, $method);
        }

        return sprintf('%sの%s。%s', $pageLabel, $actionLabel, $this->actionBehaviorText($actionLabel, $method));
    }

    private function uriToPageLabel(string $uri): string
    {
        $map = [
            '/admin/accounts' => 'アカウント',
            '/ops/configurator-sessions' => '仕様書セッション',
            '/ops/quotes' => '仕様書見積',
            '/quotes' => '仕様書',
            '/admin/change-requests' => 'リクエスト',
            '/ops/change-requests' => 'リクエスト',
            '/admin/skus' => 'パーツSKU',
            '/admin/price-books' => '価格表',
            '/admin/templates' => 'テンプレート',
            '/admin/audit-logs' => '監査ログ',
            '/configurator' => 'コンフィギュレータ',
            '/admin/accounts/*/permissions' => '権限設定',
        ];

        foreach ($map as $prefix => $label) {
            if ($prefix === '/admin/accounts/*/permissions') {
                if ((bool)preg_match('#^/admin/accounts/\d+/permissions$#', $uri)) {
                    return $label;
                }
                continue;
            }
            if ($uri === $prefix || str_starts_with($uri, $prefix . '/')) {
                return $label;
            }
        }

        return 'その他ページ';
    }

    private function guessActionLabelFromUriAndMethod(string $uri, string $method): string
    {
        if ($method === 'GET' && preg_match('#/(?:index|list)$#', $uri)) {
            return '一覧';
        }
        if ($method === 'GET' && str_ends_with($uri, '/create')) {
            return '作成';
        }
        if ($method === 'GET' && str_ends_with($uri, '/edit')) {
            return '編集';
        }
        if ($method === 'GET') {
            return '詳細';
        }
        if ($method === 'POST') {
            return '登録';
        }
        if (in_array($method, ['PUT', 'PATCH'], true)) {
            return '更新';
        }
        if ($method === 'DELETE') {
            return '削除';
        }
        return '接続';
    }

    private function actionBehaviorText(string $actionLabel, string $method): string
    {
        return match ($actionLabel) {
            '一覧' => '一覧データを表示します。',
            '詳細' => '対象データの詳細を表示します。',
            '詳細編集' => '編集フォームを表示します。',
            '新規作成' => '新規作成フォームを表示します。',
            '登録' => '入力内容を新規登録します。',
            '更新' => '既存データを更新します。',
            '削除' => '既存データを削除します。',
            default => match ($method) {
                'GET' => '画面や情報を取得します。',
                'POST' => '処理を実行します。',
                'PUT', 'PATCH' => '更新処理を実行します。',
                'DELETE' => '削除処理を実行します。',
                default => '処理を実行します。',
            },
        };
    }

    /**
     * @param array<int, string> $permissions
     * @param array<int, array{method:string,uri:string,route_name:string,suggested_pattern:string}> $routeCatalog
     */
    private function buildRouteAccessSummary(array $permissions, array $routeCatalog): string
    {
        if (empty($permissions)) {
            return '未設定';
        }

        $pages = [];
        $hasCustom = false;
        foreach ($permissions as $line) {
            $parts = explode(' ', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $method = strtoupper(trim($parts[0]));
            $pattern = trim($parts[1]);
            $matchedAny = false;

            foreach ($routeCatalog as $route) {
                if (strtoupper((string)$route['method']) !== $method) {
                    continue;
                }
                if (!$this->salesRoutePermissionService->pathMatchesPattern($pattern, (string)$route['uri'])) {
                    continue;
                }

                $matchedAny = true;
                [$pageLabel, $actionLabel] = $this->routeNameToPageAction((string)$route['route_name']);
                if (!isset($pages[$pageLabel])) {
                    $pages[$pageLabel] = [];
                }
                if (!in_array($actionLabel, $pages[$pageLabel], true)) {
                    $pages[$pageLabel][] = $actionLabel;
                }
            }

            if (!$matchedAny) {
                $hasCustom = true;
            }
        }

        if (empty($pages) && $hasCustom) {
            return 'カスタム権限のみ';
        }
        if (empty($pages)) {
            return '未設定';
        }

        $chunks = [];
        foreach ($pages as $page => $actions) {
            $chunks[] = $page . '：' . implode('、', $actions);
        }
        if ($hasCustom) {
            $chunks[] = 'その他：カスタム権限';
        }

        return implode("\n", $chunks);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function routeNameToPageAction(string $routeName): array
    {
        $name = trim($routeName);
        if ($name === '') {
            return ['その他ページ', '接続'];
        }

        $pageMap = [
            'admin.accounts' => 'アカウント',
            'ops.sessions' => '仕様書セッション',
            'ops.quotes' => '仕様書見積',
            'admin.change-requests' => 'リクエスト',
            'ops.change-requests' => 'リクエスト',
            'admin.skus' => 'パーツ(SKU)',
            'admin.price-books' => '価格表',
            'admin.templates' => 'テンプレート',
            'admin.audit-logs' => '監査ログ',
            'quotes' => '仕様書',
        ];

        $pageLabel = 'その他ページ';
        foreach ($pageMap as $prefix => $label) {
            if ($name === $prefix || str_starts_with($name, $prefix . '.')) {
                $pageLabel = $label;
                break;
            }
        }

        $segments = explode('.', $name);
        $actionKey = strtolower((string)end($segments));
        $actionMap = [
            'index' => '一覧',
            'show' => '詳細',
            'edit' => '編集',
            'create' => '作成',
            'store' => '登録',
            'update' => '更新',
            'destroy' => '削除',
        ];
        $actionLabel = $actionMap[$actionKey] ?? '接続';

        return [$pageLabel, $actionLabel];
    }
}
