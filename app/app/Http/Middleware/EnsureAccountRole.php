<?php

namespace App\Http\Middleware;

use App\Services\SalesRoutePermissionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAccountRole
{
    public function __construct(
        private readonly SalesRoutePermissionService $salesRoutePermissionService
    ) {
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        if (empty($roles)) {
            return $next($request);
        }

        $userId = (int)$user->id;
        $contextAccountId = $this->salesRoutePermissionService->resolveAccountContextId($request);
        $hasSalesRole = $contextAccountId !== null
            ? $this->salesRoutePermissionService->userHasSalesRoleInAccount($userId, $contextAccountId)
            : $this->salesRoutePermissionService->userHasSalesRole($userId);
        if ($hasSalesRole) {
            if ($this->salesRoutePermissionService->canSalesAccessRequest($request, $userId)) {
                return $next($request);
            }

            $nonSalesRoles = array_values(array_filter(
                $roles,
                static fn (string $role): bool => $role !== 'sales'
            ));

            if (!empty($nonSalesRoles)) {
                $hasNonSalesRole = DB::table('account_user')
                    ->where('user_id', $userId)
                    ->whereIn('role', $nonSalesRoles)
                    ->exists();
                if ($hasNonSalesRole) {
                    return $next($request);
                }
            }

            abort(403);
        }

        $hasRole = DB::table('account_user')
            ->where('user_id', $user->id)
            ->whereIn('role', $roles)
            ->exists();

        if (!$hasRole) {
            abort(403);
        }

        return $next($request);
    }
}
