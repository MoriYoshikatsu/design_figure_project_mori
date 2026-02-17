<?php

namespace App\Http\Middleware;

use App\Services\WorkPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureWorkRouteAccess
{
    public function __construct(
        private readonly WorkPermissionService $workPermissionService
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $allowed = $this->workPermissionService->allowsRequest($request, (int)$user->id);
        if (!$allowed) {
            abort(403);
        }

        return $next($request);
    }
}
