<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\SalesRoutePermissionService;
use App\Services\WorkPermissionService;
use App\Services\WorkChangeRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AccountController extends Controller
{
    public function __construct(
        private readonly SalesRoutePermissionService $salesRoutePermissionService,
        private readonly WorkPermissionService $workPermissionService
    ) {
    }

    public function index(Request $request)
    {
        $isDate = static fn (string $v): bool => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $v);

        $q = trim((string)$request->input('q', ''));
        $accountType = (string)$request->input('account_type', '');
        $role = (string)$request->input('role', '');
        $hasAssignee = (string)$request->input('has_assignee', '');
        $hasMemo = (string)$request->input('has_memo', '');
        $createdFrom = (string)$request->input('created_from', '');
        $createdTo = (string)$request->input('created_to', '');
        $updatedFrom = (string)$request->input('updated_from', '');
        $updatedTo = (string)$request->input('updated_to', '');

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
            ->whereNull('a.deleted_at')
            ->select('a.*')
            ->selectSub($roleList, 'role_list')
            ->selectSub($roleSummary, 'role_summary')
            ->selectSub($memberSummary, 'member_summary')
            ->selectSub($fallbackUserName, 'fallback_user_name');
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->whereRaw('cast(a.id as text) ilike ?', ["%{$q}%"])
                    ->orWhere('a.internal_name', 'ilike', "%{$q}%")
                    ->orWhere('a.assignee_name', 'ilike', "%{$q}%")
                    ->orWhere('a.account_type', 'ilike', "%{$q}%")
                    ->orWhere('a.memo', 'ilike', "%{$q}%")
                    ->orWhereExists(function ($sq) use ($q) {
                        $sq->selectRaw('1')
                            ->from('account_user as au')
                            ->join('users as u', 'u.id', '=', 'au.user_id')
                            ->whereColumn('au.account_id', 'a.id')
                            ->where(function ($userSub) use ($q) {
                                $userSub->where('u.name', 'ilike', "%{$q}%")
                                    ->orWhere('u.email', 'ilike', "%{$q}%");
                            });
                    });
            });
        }
        if ($accountType !== '') {
            $query->where('a.account_type', $accountType);
        }
        if ($role !== '') {
            $query->whereExists(function ($sq) use ($role) {
                $sq->selectRaw('1')
                    ->from('account_user as au')
                    ->whereColumn('au.account_id', 'a.id')
                    ->where('au.role', $role);
            });
        }
        if ($hasAssignee === 'with') {
            $query->whereNotNull('a.assignee_name')->where('a.assignee_name', '<>', '');
        } elseif ($hasAssignee === 'without') {
            $query->where(function ($sub) {
                $sub->whereNull('a.assignee_name')->orWhere('a.assignee_name', '');
            });
        }
        if ($hasMemo === 'with') {
            $query->whereNotNull('a.memo')->where('a.memo', '<>', '');
        } elseif ($hasMemo === 'without') {
            $query->where(function ($sub) {
                $sub->whereNull('a.memo')->orWhere('a.memo', '');
            });
        }
        if ($createdFrom !== '' && $isDate($createdFrom)) {
            $query->whereDate('a.created_at', '>=', $createdFrom);
        }
        if ($createdTo !== '' && $isDate($createdTo)) {
            $query->whereDate('a.created_at', '<=', $createdTo);
        }
        if ($updatedFrom !== '' && $isDate($updatedFrom)) {
            $query->whereDate('a.updated_at', '>=', $updatedFrom);
        }
        if ($updatedTo !== '' && $isDate($updatedTo)) {
            $query->whereDate('a.updated_at', '<=', $updatedTo);
        }

        $accounts = $query->orderBy('a.id', 'desc')->limit(200)->get();
        $accountIds = $accounts->pluck('id')->map(fn ($v) => (int)$v)->all();
        $userId = (int)($request->user()?->id ?? 0);

        $permissionMap = $this->collectAccountPermissionLines($accountIds);
        $canCreateAccount = $userId > 0
            && $this->canUserAccessPath(
                $userId,
                'POST',
                route('work.accounts.edit-request.create', [], false)
            );

        $routeCatalog = $this->salesRoutePermissionService->routeCatalog();
        foreach ($accounts as $account) {
            $aid = (int)$account->id;
            $previews = $permissionMap[$aid] ?? [];
            $account->route_access_summary = $this->buildRouteAccessSummary($previews, $routeCatalog);
            $account->can_request_delete = $userId > 0
                && $this->canUserAccessPath(
                    $userId,
                    'POST',
                    route('work.accounts.edit-request.delete', ['id' => $aid], false)
                );
        }

        return view('work.accounts.index', [
            'accounts' => $accounts,
            'canCreateAccount' => $canCreateAccount,
            'filters' => [
                'q' => $q,
                'account_type' => $accountType,
                'role' => $role,
                'has_assignee' => $hasAssignee,
                'has_memo' => $hasMemo,
                'created_from' => $createdFrom,
                'created_to' => $createdTo,
                'updated_from' => $updatedFrom,
                'updated_to' => $updatedTo,
            ],
            'accountTypeOptions' => ['B2B', 'B2C'],
            'roleOptions' => ['admin', 'sales', 'customer'],
            'presenceOptions' => [
                'with' => 'あり',
                'without' => 'なし',
            ],
        ]);
    }

    public function edit(int $id)
    {
        $account = DB::table('accounts')->whereNull('deleted_at')->where('id', $id)->first();
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

        return view('work.accounts.edit', [
            'account' => $account,
            'members' => $members,
            'roleCounts' => $roleCounts,
        ]);
    }

    public function permissions(int $id)
    {
        $account = DB::table('accounts')->whereNull('deleted_at')->where('id', $id)->first();
        if (!$account) abort(404);

        $salesRouteViewData = $this->buildSalesRouteViewData($id);

        return view('work.accounts.permissions', array_merge([
            'account' => $account,
        ], $salesRouteViewData));
    }

    public function update(Request $request, int $id)
    {
        $account = DB::table('accounts')->whereNull('deleted_at')->where('id', $id)->first();
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

        $after = [
            'account_type' => $data['account_type'],
            'internal_name' => $internal,
            'memo' => $memo,
            'assignee_name' => $assigneeName,
        ];

        app(WorkChangeRequestService::class)->queueUpdate(
            'account',
            $id,
            (array)$account,
            $after,
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        return redirect()->route('work.accounts.edit', $id)->with('status', 'アカウントの更新申請を送信しました');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'role' => 'required|in:admin,sales,customer',
            'internal_name' => 'nullable|string|max:255',
            'memo' => 'nullable|string|max:5000',
            'assignee_name' => 'nullable|string|max:255',
        ]);

        $role = (string)$data['role'];
        $accountType = $role === 'customer' ? 'B2C' : 'B2B';
        $internal = trim((string)($data['internal_name'] ?? ''));
        if ($internal === '') $internal = null;
        $memo = trim((string)($data['memo'] ?? ''));
        if ($memo === '') $memo = null;
        $assigneeName = trim((string)($data['assignee_name'] ?? ''));
        if ($assigneeName === '') $assigneeName = null;

        $after = [
            'account_type' => $accountType,
            'role' => $role,
            'internal_name' => $internal,
            'memo' => $memo,
            'assignee_name' => $assigneeName,
        ];

        app(WorkChangeRequestService::class)->queueCreate(
            'account',
            $after,
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        return redirect()->route('work.accounts.index')->with('status', 'アカウントの作成申請を送信しました');
    }

    public function destroy(Request $request, int $id)
    {
        $account = DB::table('accounts')->whereNull('deleted_at')->where('id', $id)->first();
        if (!$account) abort(404);

        app(WorkChangeRequestService::class)->queueDelete(
            'account',
            $id,
            (array)$account,
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        return redirect()->route('work.accounts.index')->with('status', 'アカウントの削除申請を送信しました');
    }

    public function updateMemberMemo(Request $request, int $id, int $userId)
    {
        $account = DB::table('accounts')->whereNull('deleted_at')->where('id', $id)->first();
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

        $after = [
            'account_id' => $id,
            'user_id' => $userId,
            'memo' => $memo,
        ];

        app(WorkChangeRequestService::class)->queueUpdate(
            'account_user_memo',
            $id,
            $before,
            $after,
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        return redirect()->route('work.accounts.edit', $id)->with('status', '権限設定メモの更新申請を送信しました');
    }

    public function storeSalesRoutePermission(Request $request, int $id)
    {
        $account = DB::table('accounts')->whereNull('deleted_at')->where('id', $id)->first();
        if (!$account) abort(404);

        if ($request->boolean('catalog_sync')) {
            $data = $request->validate([
                'catalog_permissions' => 'array',
                'catalog_permissions.*' => 'string|max:400',
            ]);

            $before = $this->snapshotAccountPermissionsForRequest($id);

            app(WorkChangeRequestService::class)->queueUpdate(
                'account_sales_route_permission_sync',
                $id,
                $before,
                [
                    'account_id' => $id,
                    'catalog_permissions' => $data['catalog_permissions'] ?? [],
                ],
                (int)$request->user()->id,
                (string)$request->input('comment', '')
            );

            return redirect()->route('work.accounts.edit', $id)->with('status', 'チェックボックス許可の反映申請を送信しました');
        }

        $data = $request->validate([
            'http_method' => 'required|string|max:10',
            'uri_pattern' => 'required|string|max:255',
            'source' => 'nullable|in:checkbox,manual',
            'memo' => 'nullable|string|max:5000',
        ]);

        $method = $this->salesRoutePermissionService->normalizeMethod((string)$data['http_method']);
        $pattern = $this->workPermissionService->normalizePath((string)$data['uri_pattern']);
        if (
            !in_array($method, $this->salesRoutePermissionService->allowedMethods(), true)
            || !$this->isValidUriPattern($pattern)
        ) {
            return redirect()
                ->route('work.accounts.edit', $id)
                ->withErrors(['uri_pattern' => 'URIパターンかHTTPメソッドが不正です。'])
                ->withInput();
        }

        $memo = trim((string)($data['memo'] ?? ''));
        if ($memo === '') {
            $memo = null;
        }
        $source = (string)($data['source'] ?? 'manual');
        $userId = (int)auth()->id();

        $existingCatalog = DB::table('work_permission_catalog')
            ->where('http_method', $method)
            ->where('uri_pattern', $pattern)
            ->first(['id']);
        $existing = null;
        if ($existingCatalog) {
            $existing = $this->findAccountPermissionByCatalogId($id, (int)$existingCatalog->id);
        }

        if ($existing) {
            app(WorkChangeRequestService::class)->queueUpdate(
                'account_sales_route_permission',
                (int)($existing['permission_catalog_id'] ?? 0),
                $existing,
                [
                    'account_id' => $id,
                    'permission_catalog_id' => (int)($existing['permission_catalog_id'] ?? 0),
                    'http_method' => $method,
                    'uri_pattern' => $pattern,
                    'source' => $source,
                    'active' => true,
                    'memo' => $memo,
                ],
                $userId > 0 ? $userId : 0,
                (string)$request->input('comment', '')
            );
            return redirect()->route('work.accounts.edit', $id)->with('status', 'Salesルート許可の更新申請を送信しました');
        }

        app(WorkChangeRequestService::class)->queueCreate(
            'account_sales_route_permission',
            [
                'account_id' => $id,
                'permission_catalog_id' => $existingCatalog ? (int)$existingCatalog->id : 0,
                'http_method' => $method,
                'uri_pattern' => $pattern,
                'source' => $source,
                'active' => true,
                'memo' => $memo,
            ],
            $userId > 0 ? $userId : 0,
            (string)$request->input('comment', '')
        );

        return redirect()->route('work.accounts.edit', $id)->with('status', 'Salesルート許可の追加申請を送信しました');
    }

    public function updateSalesRoutePermission(Request $request, int $id, int $permId)
    {
        $perm = $this->findAccountPermissionByCatalogId($id, $permId);
        if (!$perm) abort(404);

        $data = $request->validate([
            'http_method' => 'required|string|max:10',
            'uri_pattern' => 'required|string|max:255',
            'source' => 'required|in:checkbox,manual',
            'active' => 'nullable|boolean',
            'memo' => 'nullable|string|max:5000',
        ]);

        $method = $this->salesRoutePermissionService->normalizeMethod((string)$data['http_method']);
        $pattern = $this->workPermissionService->normalizePath((string)$data['uri_pattern']);
        if (
            !in_array($method, $this->salesRoutePermissionService->allowedMethods(), true)
            || !$this->isValidUriPattern($pattern)
        ) {
            return redirect()
                ->route('work.accounts.edit', $id)
                ->withErrors(['uri_pattern' => 'URIパターンかHTTPメソッドが不正です。'])
                ->withInput();
        }

        $memo = trim((string)($data['memo'] ?? ''));
        if ($memo === '') {
            $memo = null;
        }
        $active = filter_var($data['active'] ?? false, FILTER_VALIDATE_BOOL);

        app(WorkChangeRequestService::class)->queueUpdate(
            'account_sales_route_permission',
            $permId,
            $perm,
            [
                'account_id' => $id,
                'permission_catalog_id' => $permId,
                'http_method' => $method,
                'uri_pattern' => $pattern,
                'source' => $data['source'],
                'active' => $active,
                'memo' => $memo,
            ],
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        return redirect()->route('work.accounts.edit', $id)->with('status', 'Salesルート許可の更新申請を送信しました');
    }

    public function destroySalesRoutePermission(Request $request, int $id, int $permId)
    {
        $perm = $this->findAccountPermissionByCatalogId($id, $permId);
        if (!$perm) abort(404);

        app(WorkChangeRequestService::class)->queueDelete(
            'account_sales_route_permission',
            $permId,
            $perm,
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        return redirect()->route('work.accounts.edit', $id)->with('status', 'Salesルート許可の削除申請を送信しました');
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
     *   salesRouteCatalog: array<int, array{method:string,uri:string,route_name:string,suggested_pattern:string,memo:string,group_key:string,group_label:string,group_order:int,catalog_index:int,catalog_number:string}>,
     *   salesRouteCatalogGroups: array<int, array{group_key:string,group_label:string,items:array<int, array{method:string,uri:string,route_name:string,suggested_pattern:string,memo:string,group_key:string,group_label:string,group_order:int,catalog_index:int,catalog_number:string}>}>,
     *   salesRoutePermissions: \Illuminate\Support\Collection<int, object>,
     *   salesRoutePermissionStateMap: array<string, bool>,
     *   salesRouteAllowedMethods: array<int, string>
     * }
     */
    private function buildSalesRouteViewData(int $accountId): array
    {
        $salesUserIds = $this->salesUserIdsInAccount($accountId);
        $salesUserCount = count($salesUserIds);

        $grantRows = collect();
        if ($salesUserCount > 0) {
            $grantRows = DB::table('work_permission_grants as g')
                ->join('work_permission_catalog as c', 'c.id', '=', 'g.permission_catalog_id')
                ->whereIn('g.user_id', $salesUserIds)
                ->where('g.scope_type', 'account')
                ->where('g.account_id', $accountId)
                ->where('g.effect', 'allow')
                ->where('c.active', true)
                ->groupBy('c.id', 'c.http_method', 'c.uri_pattern')
                ->orderBy('c.http_method')
                ->orderBy('c.uri_pattern')
                ->select([
                    'c.id as permission_catalog_id',
                    'c.http_method',
                    'c.uri_pattern',
                    DB::raw('count(*) as grant_count'),
                    DB::raw('sum(case when g.active then 1 else 0 end) as active_count'),
                    DB::raw("coalesce(max(g.memo), '') as memo_sample"),
                ])
                ->get();
        }

        $permissions = $grantRows->map(function (object $row) use ($salesUserCount): object {
            $memo = $this->stripGrantMemoSource((string)($row->memo_sample ?? ''));
            return (object)[
                'id' => (int)$row->permission_catalog_id,
                'http_method' => (string)$row->http_method,
                'uri_pattern' => (string)$row->uri_pattern,
                'source' => $this->grantMemoSource((string)($row->memo_sample ?? '')),
                'active' => (int)($row->active_count ?? 0) >= $salesUserCount,
                'memo' => $memo,
            ];
        })->values();

        $permissionStateMap = [];
        foreach ($grantRows as $row) {
            $k = strtoupper((string)$row->http_method) . ' ' . (string)$row->uri_pattern;
            $permissionStateMap[$k] = $salesUserCount > 0 && (int)($row->active_count ?? 0) >= $salesUserCount;
        }

        $routeCatalogRaw = $this->salesRoutePermissionService->routeCatalog();
        $routeCatalog = array_map(function (array $row): array {
            [$groupKey, $groupLabel, $groupOrder] = $this->resolveCatalogGroup(
                (string)($row['uri'] ?? '/'),
                (string)($row['route_name'] ?? '')
            );
            $row['memo'] = $this->buildCatalogRouteMemo($row);
            $row['group_key'] = $groupKey;
            $row['group_label'] = $groupLabel;
            $row['group_order'] = $groupOrder;
            return $row;
        }, $routeCatalogRaw);

        usort($routeCatalog, function (array $a, array $b): int {
            $groupCmp = ((int)($a['group_order'] ?? 999)) <=> ((int)($b['group_order'] ?? 999));
            if ($groupCmp !== 0) {
                return $groupCmp;
            }

            $uriCmp = strcmp((string)($a['uri'] ?? ''), (string)($b['uri'] ?? ''));
            if ($uriCmp !== 0) {
                return $uriCmp;
            }

            $patternCmp = strcmp((string)($a['suggested_pattern'] ?? ''), (string)($b['suggested_pattern'] ?? ''));
            if ($patternCmp !== 0) {
                return $patternCmp;
            }

            $methodCmp = $this->methodSortOrder((string)($a['method'] ?? ''))
                <=> $this->methodSortOrder((string)($b['method'] ?? ''));
            if ($methodCmp !== 0) {
                return $methodCmp;
            }

            return strcmp((string)($a['route_name'] ?? ''), (string)($b['route_name'] ?? ''));
        });

        foreach ($routeCatalog as $idx => &$row) {
            $index = $idx + 1;
            $row['catalog_index'] = $index;
            $row['catalog_number'] = str_pad((string)$index, 3, '0', STR_PAD_LEFT);
        }
        unset($row);

        $routeCatalogGroups = [];
        foreach ($routeCatalog as $row) {
            $groupKey = (string)($row['group_key'] ?? 'other');
            if (!isset($routeCatalogGroups[$groupKey])) {
                $routeCatalogGroups[$groupKey] = [
                    'group_key' => $groupKey,
                    'group_label' => (string)($row['group_label'] ?? 'その他'),
                    'items' => [],
                ];
            }
            $routeCatalogGroups[$groupKey]['items'][] = $row;
        }

        return [
            'salesRouteCatalog' => $routeCatalog,
            'salesRouteCatalogGroups' => array_values($routeCatalogGroups),
            'salesRoutePermissions' => $permissions,
            'salesRoutePermissionStateMap' => $permissionStateMap,
            'salesRouteAllowedMethods' => $this->salesRoutePermissionService->allowedMethods(),
        ];
    }

    /**
     * @param array<int, int> $accountIds
     * @return array<int, array<int, string>>
     */
    private function collectAccountPermissionLines(array $accountIds): array
    {
        if (empty($accountIds)) {
            return [];
        }

        $rows = DB::table('work_permission_grants as g')
            ->join('work_permission_catalog as c', 'c.id', '=', 'g.permission_catalog_id')
            ->join('account_user as au', function ($join): void {
                $join->on('au.user_id', '=', 'g.user_id')
                    ->on('au.account_id', '=', 'g.account_id')
                    ->where('au.role', '=', 'sales');
            })
            ->whereIn('g.account_id', $accountIds)
            ->where('g.scope_type', 'account')
            ->where('g.effect', 'allow')
            ->where('g.active', true)
            ->where('c.active', true)
            ->distinct()
            ->orderBy('g.account_id')
            ->orderBy('c.http_method')
            ->orderBy('c.uri_pattern')
            ->get(['g.account_id', 'c.http_method', 'c.uri_pattern']);

        $map = [];
        foreach ($rows as $row) {
            $aid = (int)$row->account_id;
            if (!isset($map[$aid])) {
                $map[$aid] = [];
            }
            $map[$aid][] = strtoupper((string)$row->http_method) . ' ' . (string)$row->uri_pattern;
        }

        return $map;
    }

    /**
     * @return array<int, int>
     */
    private function salesUserIdsInAccount(int $accountId): array
    {
        if ($accountId <= 0) {
            return [];
        }

        return DB::table('account_user')
            ->where('account_id', $accountId)
            ->where('role', 'sales')
            ->pluck('user_id')
            ->map(fn ($v): int => (int)$v)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{account_id:int,permission_catalog_id:int,http_method:string,uri_pattern:string,source:string,active:bool,memo:?string}>
     */
    private function snapshotAccountPermissionsForRequest(int $accountId): array
    {
        $salesUserIds = $this->salesUserIdsInAccount($accountId);
        if (empty($salesUserIds)) {
            return [];
        }

        $rows = DB::table('work_permission_grants as g')
            ->join('work_permission_catalog as c', 'c.id', '=', 'g.permission_catalog_id')
            ->whereIn('g.user_id', $salesUserIds)
            ->where('g.scope_type', 'account')
            ->where('g.effect', 'allow')
            ->where('g.account_id', $accountId)
            ->where('c.active', true)
            ->groupBy('c.id', 'c.http_method', 'c.uri_pattern')
            ->orderBy('c.http_method')
            ->orderBy('c.uri_pattern')
            ->select([
                'c.id as permission_catalog_id',
                'c.http_method',
                'c.uri_pattern',
                DB::raw('sum(case when g.active then 1 else 0 end) as active_count'),
                DB::raw("coalesce(max(g.memo), '') as memo_sample"),
            ])
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $memoSample = (string)($row->memo_sample ?? '');
            $result[] = [
                'account_id' => $accountId,
                'permission_catalog_id' => (int)$row->permission_catalog_id,
                'http_method' => strtoupper((string)$row->http_method),
                'uri_pattern' => (string)$row->uri_pattern,
                'source' => $this->grantMemoSource($memoSample),
                'active' => (int)($row->active_count ?? 0) > 0,
                'memo' => $this->stripGrantMemoSource($memoSample),
            ];
        }

        return $result;
    }

    /**
     * @return array{account_id:int,permission_catalog_id:int,http_method:string,uri_pattern:string,source:string,active:bool,memo:?string}|null
     */
    private function findAccountPermissionByCatalogId(int $accountId, int $catalogId): ?array
    {
        if ($accountId <= 0 || $catalogId <= 0) {
            return null;
        }

        $salesUserIds = $this->salesUserIdsInAccount($accountId);
        if (empty($salesUserIds)) {
            return null;
        }

        $row = DB::table('work_permission_grants as g')
            ->join('work_permission_catalog as c', 'c.id', '=', 'g.permission_catalog_id')
            ->whereIn('g.user_id', $salesUserIds)
            ->where('g.scope_type', 'account')
            ->where('g.effect', 'allow')
            ->where('g.account_id', $accountId)
            ->where('g.permission_catalog_id', $catalogId)
            ->where('c.active', true)
            ->groupBy('c.id', 'c.http_method', 'c.uri_pattern')
            ->select([
                'c.id as permission_catalog_id',
                'c.http_method',
                'c.uri_pattern',
                DB::raw('sum(case when g.active then 1 else 0 end) as active_count'),
                DB::raw("coalesce(max(g.memo), '') as memo_sample"),
            ])
            ->first();
        if (!$row) {
            return null;
        }

        $memoSample = (string)($row->memo_sample ?? '');
        return [
            'account_id' => $accountId,
            'permission_catalog_id' => (int)$row->permission_catalog_id,
            'http_method' => strtoupper((string)$row->http_method),
            'uri_pattern' => (string)$row->uri_pattern,
            'source' => $this->grantMemoSource($memoSample),
            'active' => (int)($row->active_count ?? 0) > 0,
            'memo' => $this->stripGrantMemoSource($memoSample),
        ];
    }

    private function grantMemoSource(?string $memo): string
    {
        $memo = trim((string)$memo);
        if (str_starts_with($memo, 'source:checkbox;') || str_starts_with($memo, 'migrated:legacy-sales')) {
            return 'checkbox';
        }
        return 'manual';
    }

    private function stripGrantMemoSource(?string $memo): ?string
    {
        $memo = trim((string)$memo);
        if ($memo === '') {
            return null;
        }
        if (str_starts_with($memo, 'source:checkbox;')) {
            $value = trim(substr($memo, strlen('source:checkbox;')));
            return $value === '' ? null : $value;
        }
        if (str_starts_with($memo, 'source:manual;')) {
            $value = trim(substr($memo, strlen('source:manual;')));
            return $value === '' ? null : $value;
        }
        return $memo;
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

    /**
     * @return array{0:string,1:string,2:int}
     */
    private function resolveCatalogGroup(string $uri, string $routeName): array
    {
        $path = '/' . ltrim($uri, '/');
        $groups = [
            '/work/accounts' => ['accounts', 'アカウント', 10],
            '/work/sessions' => ['sessions', '仕様書セッション', 20],
            '/work/quotes' => ['quotes', '仕様書見積', 30],
            '/quotes' => ['quotes_public', '仕様書公開', 40],
            '/work/skus' => ['skus', 'パーツ(SKU)', 50],
            '/work/price-books' => ['price_books', '価格表', 60],
            '/work/templates' => ['templates', 'テンプレート', 70],
            '/work/change-requests' => ['change_requests', '編集承認リクエスト', 80],
            '/work/audit-logs' => ['audit_logs', '監査ログ', 90],
            '/configurator' => ['configurator', 'コンフィギュレータ', 100],
            '/work' => ['work_other', '業務ページ（その他）', 900],
        ];

        foreach ($groups as $prefix => $meta) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return $meta;
            }
        }

        $routeName = trim($routeName);
        if ($routeName !== '') {
            [$pageLabel] = $this->routeNameToPageAction($routeName);
            return ['other', $pageLabel, 950];
        }

        return ['other', 'その他', 999];
    }

    private function methodSortOrder(string $method): int
    {
        return match (strtoupper($method)) {
            'GET' => 10,
            'POST' => 20,
            'PUT' => 30,
            'PATCH' => 31,
            'DELETE' => 40,
            default => 99,
        };
    }

    private function uriToPageLabel(string $uri): string
    {
        $map = [
            '/work/accounts' => 'アカウント',
            '/work/sessions' => '仕様書セッション',
            '/work/quotes' => '仕様書見積',
            '/quotes' => '仕様書',
            '/work/change-requests' => 'リクエスト',
            '/work/skus' => 'パーツSKU',
            '/work/price-books' => '価格表',
            '/work/templates' => 'テンプレート',
            '/work/audit-logs' => '監査ログ',
            '/configurator' => 'コンフィギュレータ',
            '/work/accounts/*/permissions' => '権限設定',
        ];

        foreach ($map as $prefix => $label) {
            if ($prefix === '/work/accounts/*/permissions') {
                if ((bool)preg_match('#^/work/accounts/\d+/permissions$#', $uri)) {
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

    private function canUserAccessPath(int $userId, string $method, string $path): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $request = Request::create($path, strtoupper($method));
        return $this->workPermissionService->allowsRequest($request, $userId);
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
            'work.accounts' => 'アカウント',
            'work.sessions' => '仕様書セッション',
            'work.quotes' => '仕様書見積',
            'work.change-requests' => 'リクエスト',
            'work.change-requests' => 'リクエスト',
            'work.skus' => 'パーツ(SKU)',
            'work.price-books' => '価格表',
            'work.templates' => 'テンプレート',
            'work.audit-logs' => '監査ログ',
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
            'delete' => '削除',
            'store' => '登録',
            'update' => '更新',
            'destroy' => '削除',
        ];
        $actionLabel = $actionMap[$actionKey] ?? '接続';

        return [$pageLabel, $actionLabel];
    }
}
