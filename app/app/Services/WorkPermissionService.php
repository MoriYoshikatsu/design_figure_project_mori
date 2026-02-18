<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class WorkPermissionService
{
    /** @var array<int, string> */
    private const METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', '*'];

    public function normalizePath(string $value): string
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

    public function normalizeMethod(string $value): string
    {
        $m = strtoupper(trim($value));
        return in_array($m, self::METHODS, true) ? $m : 'GET';
    }

    public function pathMatchesPattern(string $pattern, string $path): bool
    {
        $pattern = $this->normalizePath($pattern);
        if ($pattern === '*') {
            return true;
        }

        $quoted = preg_quote($pattern, '#');
        $regex = '#^' . str_replace('\\*', '.*', $quoted) . '$#';
        return (bool)preg_match($regex, $path);
    }

    public function methodMatches(string $ruleMethod, string $method): bool
    {
        $ruleMethod = strtoupper($ruleMethod);
        $method = strtoupper($method);
        return $ruleMethod === '*' || $ruleMethod === $method;
    }

    public function resolveAccountContextId(Request $request, ?int $userId = null): ?int
    {
        $path = $this->normalizePath('/' . ltrim((string)$request->path(), '/'));
        $userId = (int)($userId ?? ($request->user()?->id ?? 0));

        if (preg_match('#^/work/accounts/(\d+)(?:/|$)#', $path, $m)) {
            return (int)$m[1];
        }

        if (preg_match('#^/work/quotes/(\d+)(?:/|$)#', $path, $m)) {
            $accountId = (int)DB::table('quotes')
                ->whereNull('deleted_at')
                ->where('id', (int)$m[1])
                ->value('account_id');
            return $accountId > 0 ? $accountId : null;
        }

        if (preg_match('#^/work/sessions/(\d+)(?:/|$)#', $path, $m)) {
            $accountId = (int)DB::table('configurator_sessions')
                ->where('id', (int)$m[1])
                ->value('account_id');
            return $accountId > 0 ? $accountId : null;
        }

        if (preg_match('#^/work/change-requests/(\d+)(?:/|$)#', $path, $m)) {
            return $this->resolveAccountIdFromChangeRequest((int)$m[1]);
        }

        return null;
    }

    public function allowsRequest(Request $request, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $path = $this->normalizePath('/' . ltrim((string)$request->path(), '/'));
        $method = strtoupper($request->method());
        $pathsToMatch = [$path];
        if ($method === 'GET') {
            if (preg_match('#^/work/price-books/\d+$#', $path)) {
                $pathsToMatch[] = $path . '/edit';
            }
            if (preg_match('#^/work/templates/\d+$#', $path)) {
                $pathsToMatch[] = $path . '/edit';
            }
        }
        $accountId = $this->resolveAccountContextId($request, $userId);
        $accountScopeIds = $accountId !== null
            ? [$accountId]
            : $this->resolveUserAccountIds($userId);

        $rows = DB::table('work_permission_grants as g')
            ->join('work_permission_catalog as c', 'c.id', '=', 'g.permission_catalog_id')
            ->where('g.user_id', $userId)
            ->where('g.active', true)
            ->where('c.active', true)
            ->where(function ($q) use ($accountScopeIds) {
                $q->where('g.scope_type', 'global');
                if (!empty($accountScopeIds)) {
                    $q->orWhere(function ($aq) use ($accountScopeIds) {
                        $aq->where('g.scope_type', 'account')
                            ->whereIn('g.account_id', $accountScopeIds);
                    });
                }
            })
            ->get([
                'g.effect',
                'g.scope_type',
                'c.http_method',
                'c.uri_pattern',
            ]);

        $has = [
            'deny_account' => false,
            'deny_global' => false,
            'allow_account' => false,
            'allow_global' => false,
        ];

        foreach ($rows as $row) {
            $ruleMethod = strtoupper((string)$row->http_method);
            $rulePattern = (string)$row->uri_pattern;
            if (!$this->methodMatches($ruleMethod, $method)) {
                continue;
            }
            $matched = false;
            foreach ($pathsToMatch as $candidatePath) {
                if ($this->pathMatchesPattern($rulePattern, $candidatePath)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                continue;
            }

            $effect = strtolower((string)$row->effect);
            $scope = strtolower((string)$row->scope_type);
            if ($effect === 'deny' && $scope === 'account') {
                $has['deny_account'] = true;
            } elseif ($effect === 'deny' && $scope === 'global') {
                $has['deny_global'] = true;
            } elseif ($effect === 'allow' && $scope === 'account') {
                $has['allow_account'] = true;
            } elseif ($effect === 'allow' && $scope === 'global') {
                $has['allow_global'] = true;
            }
        }

        if ($has['deny_account']) return false;
        if ($has['deny_global']) return false;
        if ($has['allow_account']) return true;
        if ($has['allow_global']) return true;

        return false;
    }

    /**
     * @return array<int, array{permission_key:string,http_method:string,uri_pattern:string,label:string,default_scope:string}>
     */
    public function routeCatalog(): array
    {
        $rows = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $this->normalizePath('/' . ltrim((string)$route->uri(), '/'));
            if (!$this->isWorkRoute($uri)) {
                continue;
            }

            $routeName = (string)($route->getName() ?? '');
            foreach ($route->methods() as $method) {
                $method = strtoupper((string)$method);
                if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                    continue;
                }

                $pattern = preg_replace('#\{[^/]+\}#', '*', $uri) ?? $uri;
                $key = strtolower($method . ':' . $pattern);
                $rows[$key] = [
                    'permission_key' => $key,
                    'http_method' => $method,
                    'uri_pattern' => $pattern,
                    'label' => $routeName !== '' ? $routeName : ($method . ' ' . $pattern),
                    'default_scope' => $this->defaultScopeFromPattern($pattern),
                ];
            }
        }

        // wildcard for administrators
        $rows['wildcard:work'] = [
            'permission_key' => 'wildcard:work',
            'http_method' => '*',
            'uri_pattern' => '/work/*',
            'label' => 'All /work routes',
            'default_scope' => 'global',
        ];

        return array_values($rows);
    }

    public function syncCatalog(): int
    {
        $rows = $this->routeCatalog();
        $count = 0;
        foreach ($rows as $row) {
            DB::table('work_permission_catalog')->updateOrInsert(
                ['permission_key' => $row['permission_key']],
                [
                    'http_method' => $row['http_method'],
                    'uri_pattern' => $row['uri_pattern'],
                    'label' => $row['label'],
                    'default_scope' => $row['default_scope'],
                    'active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            $count++;
        }

        return $count;
    }

    public function migrateLegacySalesPermissionsToWork(): int
    {
        $this->syncCatalog();
        $migrated = 0;

        $admins = DB::table('account_user')
            ->where('role', 'admin')
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($v) => (int)$v)
            ->all();

        $wildcardId = (int)DB::table('work_permission_catalog')
            ->where('permission_key', 'wildcard:work')
            ->value('id');

        foreach ($admins as $userId) {
            DB::table('work_permission_grants')->updateOrInsert(
                [
                    'user_id' => $userId,
                    'permission_catalog_id' => $wildcardId,
                    'effect' => 'allow',
                    'scope_type' => 'global',
                    'account_id' => null,
                ],
                [
                    'active' => true,
                    'memo' => 'migrated:admin-wildcard',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            $migrated++;
        }

        if (!Schema::hasTable('account_sales_route_permissions')) {
            return $migrated;
        }

        $legacyQuery = DB::table('account_sales_route_permissions')
            ->where('active', true)
            ->orderBy('id');
        if (Schema::hasColumn('account_sales_route_permissions', 'deleted_at')) {
            $legacyQuery->whereNull('deleted_at');
        }
        $legacy = $legacyQuery->get();

        foreach ($legacy as $row) {
            $accountId = (int)$row->account_id;
            $method = strtoupper((string)$row->http_method);
            $pattern = $this->normalizeLegacyPattern((string)$row->uri_pattern);
            $key = strtolower($method . ':' . $pattern);

            DB::table('work_permission_catalog')->updateOrInsert(
                ['permission_key' => $key],
                [
                    'http_method' => $method,
                    'uri_pattern' => $pattern,
                    'label' => 'migrated:' . $method . ' ' . $pattern,
                    'default_scope' => 'account',
                    'active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $catalogId = (int)DB::table('work_permission_catalog')
                ->where('permission_key', $key)
                ->value('id');

            $salesUsers = DB::table('account_user')
                ->where('account_id', $accountId)
                ->where('role', 'sales')
                ->pluck('user_id')
                ->map(fn ($v) => (int)$v)
                ->all();

            foreach ($salesUsers as $userId) {
                DB::table('work_permission_grants')->updateOrInsert(
                    [
                        'user_id' => $userId,
                        'permission_catalog_id' => $catalogId,
                        'effect' => 'allow',
                        'scope_type' => 'account',
                        'account_id' => $accountId,
                    ],
                    [
                        'active' => true,
                        'memo' => 'source:checkbox;migrated:legacy-sales',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
                $migrated++;
            }
        }

        return $migrated;
    }

    private function normalizeLegacyPattern(string $pattern): string
    {
        $p = $this->normalizePath($pattern);
        $p = preg_replace('#^/(admin|ops)(/|$)#', '/work$2', $p) ?? $p;
        $p = str_replace('/work/configurator-sessions', '/work/sessions', $p);
        return $p;
    }

    private function resolveAccountIdFromChangeRequest(int $id): ?int
    {
        $row = DB::table('change_requests')
            ->where('id', $id)
            ->first(['entity_type', 'entity_id']);
        if (!$row) {
            return null;
        }

        $entityType = strtolower((string)$row->entity_type);
        $entityId = (int)$row->entity_id;
        if ($entityId <= 0) {
            return null;
        }

        if (in_array($entityType, ['quote', 'quotes'], true)) {
            $accountId = (int)DB::table('quotes')
                ->whereNull('deleted_at')
                ->where('id', $entityId)
                ->value('account_id');
            return $accountId > 0 ? $accountId : null;
        }

        if (in_array($entityType, ['configurator_session', 'session'], true)) {
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

    private function isWorkRoute(string $uri): bool
    {
        if (!Str::startsWith($uri, '/work/')) {
            return false;
        }

        $excluded = [
            '/work/login',
            '/work/register',
        ];

        foreach ($excluded as $x) {
            if (Str::startsWith($uri, $x)) {
                return false;
            }
        }

        return true;
    }

    private function defaultScopeFromPattern(string $pattern): string
    {
        if (preg_match('#^/work/(accounts|quotes|sessions|change-requests)/\*#', $pattern)) {
            return 'account';
        }

        return 'global';
    }

    /**
     * @return array<int, int>
     */
    private function resolveUserAccountIds(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        return DB::table('account_user')
            ->where('user_id', $userId)
            ->orderByRaw("
                case role
                    when 'sales' then 1
                    when 'admin' then 2
                    when 'customer' then 3
                    else 9
                end
            ")
            ->orderBy('account_id')
            ->pluck('account_id')
            ->map(fn ($v): int => (int)$v)
            ->filter(fn (int $v): bool => $v > 0)
            ->values()
            ->all();
    }
}
