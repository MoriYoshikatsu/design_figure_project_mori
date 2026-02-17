<?php

namespace Tests\Unit;

use App\Services\SalesRoutePermissionService;
use PHPUnit\Framework\TestCase;

final class SalesRoutePermissionServiceTest extends TestCase
{
    public function test_path_matches_exact_pattern(): void
    {
        $service = new SalesRoutePermissionService();

        $this->assertTrue($service->pathMatchesPattern('/work/quotes/10', '/work/quotes/10'));
        $this->assertFalse($service->pathMatchesPattern('/work/quotes/10', '/work/quotes/11'));
    }

    public function test_path_matches_wildcard_prefix_pattern(): void
    {
        $service = new SalesRoutePermissionService();

        $this->assertTrue($service->pathMatchesPattern('/work/quotes/*', '/work/quotes/10'));
        $this->assertTrue($service->pathMatchesPattern('/work/quotes*', '/work/quotes/10/snapshot.pdf'));
        $this->assertFalse($service->pathMatchesPattern('/work/quotes/*', '/work/sessions/10'));
    }

    public function test_suggest_pattern_from_uri_with_parameters(): void
    {
        $service = new SalesRoutePermissionService();

        $this->assertSame('/work/quotes/*', $service->suggestPatternFromUri('/work/quotes/{id}'));
        $this->assertSame('/work/accounts/*/edit', $service->suggestPatternFromUri('/work/accounts/{id}/edit'));
        $this->assertSame('/work/skus', $service->suggestPatternFromUri('/work/skus'));
    }
}
