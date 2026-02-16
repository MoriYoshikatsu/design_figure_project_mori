<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

final class SalesRoutePermissionService
{
    /** @var array<int, string> */
    private const ALLOWED_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * @return array<int, array{method:string,uri:string,route_name:string,suggested_pattern:string}>
     */
    public function routeCatalog(): array
    {
        $rows = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $this->normalizePath('/' . ltrim((string)$route->uri(), '/'));
            if ($this->isExcludedFromCatalog($uri)) {
                continue;
            }

            $routeName = (string)($route->getName() ?? '');
            $suggested = $this->suggestPatternFromUri($uri);
            foreach ($route->methods() as $method) {
                $method = strtoupper((string)$method);
                if (!in_array($method, self::ALLOWED_METHODS, true)) {
                    continue;
                }

                $rows[] = [
                    'method' => $method,
                    'uri' => $uri,
                    'route_name' => $routeName,
                    'suggested_pattern' => $suggested,
                ];
            }
        }

        usort($rows, static function (array $a, array $b): int {
            if ($a['uri'] === $b['uri']) {
                return strcmp($a['method'], $b['method']);
            }
            return strcmp($a['uri'], $b['uri']);
        });

        // method+uri重複の除去
        $unique = [];
        $seen = [];
        foreach ($rows as $row) {
            $key = $row['method'] . ' ' . $row['uri'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $row;
        }

        return $unique;
    }

    /**
     * @return array<int, string>
     */
    public function allowedMethods(): array
    {
        return self::ALLOWED_METHODS;
    }

    public function canSalesAccessRequest(Request $request, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $accountId = $this->resolveAccountContextId($request);
        if ($accountId === null) {
            return $this->isListRoute($request) && $this->userHasSalesRole($userId);
        }

        if (!$this->userHasSalesRoleInAccount($userId, $accountId)) {
            return false;
        }

        $path = $this->normalizePath('/' . ltrim((string)$request->path(), '/'));
        $requiresExplicitAllow = $this->isAccountPermissionsPagePath($path);

        $mode = (string)(DB::table('accounts')
            ->where('id', $accountId)
            ->value('sales_route_policy_mode') ?? 'strict_allowlist');

        if ($mode === 'legacy_allow_all' && !$requiresExplicitAllow) {
            return true;
        }

        $method = strtoupper($request->method());
        if (!in_array($method, self::ALLOWED_METHODS, true)) {
            return false;
        }

        $patterns = DB::table('account_sales_route_permissions')
            ->where('account_id', $accountId)
            ->where('http_method', $method)
            ->where('active', true)
            ->orderBy('id')
            ->pluck('uri_pattern');

        // 権限設定ページだけは legacy でも明示許可が必要（初期Adminのみ）
        if ($requiresExplicitAllow) {
            foreach ($patterns as $pattern) {
                if ($this->pathMatchesPattern((string)$pattern, $path)) {
                    return true;
                }
            }
            return false;
        }

        foreach ($patterns as $pattern) {
            if ($this->pathMatchesPattern((string)$pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    public function resolveAccountContextId(Request $request): ?int
    {
        $path = $this->normalizePath('/' . ltrim((string)$request->path(), '/'));

        if (preg_match('#^/admin/accounts/(\d+)(?:/|$)#', $path, $m)) {
            return (int)$m[1];
        }

        if (preg_match('#^/(?:ops/)?quotes/(\d+)(?:/|$)#', $path, $m)) {
            $accountId = (int)DB::table('quotes')
                ->where('id', (int)$m[1])
                ->value('account_id');
            return $accountId > 0 ? $accountId : null;
        }

        if (preg_match('#^/ops/configurator-sessions/(\d+)(?:/|$)#', $path, $m)) {
            $accountId = (int)DB::table('configurator_sessions')
                ->where('id', (int)$m[1])
                ->value('account_id');
            return $accountId > 0 ? $accountId : null;
        }

        if (preg_match('#^/(?:ops|admin)/change-requests/(\d+)(?:/|$)#', $path, $m)) {
            return $this->resolveAccountIdFromChangeRequest((int)$m[1]);
        }

        return null;
    }

    public function isListRoute(Request $request): bool
    {
        $routeName = (string)($request->route()?->getName() ?? '');
        if ($routeName !== '' && preg_match('/(?:^|\.)(index|list)$/', $routeName)) {
            return true;
        }

        // 名前がない場合は index/list 文字列を含むURIを補助的に許可
        $path = $this->normalizePath('/' . ltrim((string)$request->path(), '/'));
        return (bool)preg_match('#/(index|list)(?:/|$)#', $path);
    }

    public function normalizeMethod(string $method): string
    {
        return strtoupper(trim($method));
    }

    public function normalizePattern(string $pattern): string
    {
        return $this->normalizePath($pattern);
    }

    public function pathMatchesPattern(string $pattern, string $normalizedPath): bool
    {
        $pattern = $this->normalizePattern($pattern);
        if ($pattern === '*') {
            return true;
        }

        $quoted = preg_quote($pattern, '#');
        $regex = '#^' . str_replace('\*', '.*', $quoted) . '$#';
        return (bool)preg_match($regex, $normalizedPath);
    }

    public function suggestPatternFromUri(string $uri): string
    {
        $uri = $this->normalizePath($uri);
        if (!str_contains($uri, '{')) {
            return $uri;
        }

        // /admin/accounts/{id}/permissions -> /admin/accounts/*/permissions
        return preg_replace('#\{[^/]+\}#', '*', $uri) ?? $uri;
    }

    public function userHasSalesRole(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        return DB::table('account_user')
            ->where('user_id', $userId)
            ->where('role', 'sales')
            ->exists();
    }

    public function userHasSalesRoleInAccount(int $userId, int $accountId): bool
    {
        if ($userId <= 0 || $accountId <= 0) {
            return false;
        }

        return DB::table('account_user')
            ->where('account_id', $accountId)
            ->where('user_id', $userId)
            ->where('role', 'sales')
            ->exists();
    }

    private function normalizePath(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '/';
        }

        $path = parse_url($value, PHP_URL_PATH);
        $path = is_string($path) ? $path : $value;
        $path = '/' . ltrim($path, '/');
        $path = preg_replace('#/+#', '/', $path) ?? $path;

        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        return $path;
    }

    private function isExcludedFromCatalog(string $uri): bool
    {
        if ($uri === '/up') {
            return true;
        }

        $prefixes = [
            '/login',
            '/logout',
            '/register',
            '/forgot-password',
            '/reset-password',
            '/storage',
            '/email',
            '/two-factor',
            '/user',
            '/user/confirm-password',
            '/user/confirmed-password-status',
            '/_debugbar',
            '/livewire',
        ];

        foreach ($prefixes as $prefix) {
            if (Str::startsWith($uri, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function resolveAccountIdFromChangeRequest(int $changeRequestId): ?int
    {
        $row = DB::table('change_requests')
            ->where('id', $changeRequestId)
            ->first(['entity_type', 'entity_id']);
        if (!$row) {
            return null;
        }

        $entityType = strtolower(trim((string)$row->entity_type));
        $entityId = (int)$row->entity_id;
        if ($entityId <= 0) {
            return null;
        }

        if (in_array($entityType, ['quote', 'quotes'], true)) {
            $accountId = (int)DB::table('quotes')
                ->where('id', $entityId)
                ->value('account_id');
            return $accountId > 0 ? $accountId : null;
        }

        if (in_array($entityType, ['configurator_session', 'configurator_sessions', 'session', 'sessions'], true)) {
            $accountId = (int)DB::table('configurator_sessions')
                ->where('id', $entityId)
                ->value('account_id');
            return $accountId > 0 ? $accountId : null;
        }

        if (in_array($entityType, ['account', 'accounts'], true)) {
            return $entityId;
        }

        return null;
    }

    private function isAccountPermissionsPagePath(string $path): bool
    {
        return (bool)preg_match('#^/admin/accounts/\d+/permissions$#', $path);
    }
}
