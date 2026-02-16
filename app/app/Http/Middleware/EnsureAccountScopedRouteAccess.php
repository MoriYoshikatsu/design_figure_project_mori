<?php

namespace App\Http\Middleware;

use App\Services\SalesRoutePermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAccountScopedRouteAccess
{
    public function __construct(
        private readonly SalesRoutePermissionService $salesRoutePermissionService
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $userId = (int)$user->id;
        $accountId = $this->salesRoutePermissionService->resolveAccountContextId($request);
        if ($accountId !== null) {
            if (!$this->salesRoutePermissionService->userHasSalesRoleInAccount($userId, $accountId)) {
                return $next($request);
            }
        } else {
            if (!$this->salesRoutePermissionService->userHasSalesRole($userId)) {
                return $next($request);
            }
        }

        if (!$this->salesRoutePermissionService->canSalesAccessRequest($request, $userId)) {
            abort(403);
        }

        return $next($request);
    }
}
