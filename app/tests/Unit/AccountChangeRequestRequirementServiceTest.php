<?php

namespace Tests\Unit;

use App\Services\AccountChangeRequestRequirementService;
use PHPUnit\Framework\TestCase;

final class AccountChangeRequestRequirementServiceTest extends TestCase
{
    public function test_toggleable_entity_types_include_requirement_setting_itself(): void
    {
        $service = new AccountChangeRequestRequirementService();

        $this->assertContains('account_change_request_requirement', $service->toggleableEntityTypes());
    }

    public function test_normalize_required_entity_types_filters_unknown_values_and_preserves_catalog_order(): void
    {
        $service = new AccountChangeRequestRequirementService();

        $normalized = $service->normalizeRequiredEntityTypes([
            'quote',
            'unknown_type',
            'account_change_request_requirement',
            'quote',
            'account',
        ]);

        $this->assertSame([
            'account',
            'account_change_request_requirement',
            'quote',
        ], $normalized);
    }

    public function test_delete_operation_is_always_change_request_required(): void
    {
        $service = new AccountChangeRequestRequirementService();

        $this->assertTrue($service->requiresChangeRequest(
            'part',
            123,
            0,
            ['id' => 123],
            null,
            [],
            'DELETE'
        ));
    }

    public function test_build_view_data_uses_create_update_summary_text(): void
    {
        $service = new AccountChangeRequestRequirementService();

        $viewData = $service->buildViewData(0);

        $this->assertSame('作成・更新はすべて必須', $viewData['changeRequestRequirementSummary']);
    }
}
