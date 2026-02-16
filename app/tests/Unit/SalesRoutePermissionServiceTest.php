<?php

namespace Tests\Unit;

use App\Services\SalesRoutePermissionService;
use PHPUnit\Framework\TestCase;

final class SalesRoutePermissionServiceTest extends TestCase
{
    public function test_path_matches_exact_pattern(): void
    {
        $service = new SalesRoutePermissionService();

        $this->assertTrue($service->pathMatchesPattern('/ops/quotes/10', '/ops/quotes/10'));
        $this->assertFalse($service->pathMatchesPattern('/ops/quotes/10', '/ops/quotes/11'));
    }

    public function test_path_matches_wildcard_prefix_pattern(): void
    {
        $service = new SalesRoutePermissionService();

        $this->assertTrue($service->pathMatchesPattern('/ops/quotes/*', '/ops/quotes/10'));
        $this->assertTrue($service->pathMatchesPattern('/ops/quotes*', '/ops/quotes/10/snapshot.pdf'));
        $this->assertFalse($service->pathMatchesPattern('/ops/quotes/*', '/ops/configurator-sessions/10'));
    }

    public function test_suggest_pattern_from_uri_with_parameters(): void
    {
        $service = new SalesRoutePermissionService();

        $this->assertSame('/ops/quotes/*', $service->suggestPatternFromUri('/ops/quotes/{id}'));
        $this->assertSame('/admin/accounts/*', $service->suggestPatternFromUri('/admin/accounts/{id}/edit'));
        $this->assertSame('/admin/skus', $service->suggestPatternFromUri('/admin/skus'));
    }
}
