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

    public function test_build_view_data_hides_template_requirement_items_from_ui(): void
    {
        $service = new AccountChangeRequestRequirementService();

        $viewData = $service->buildViewData(0);
        $entityTypes = [];
        foreach ($viewData['changeRequestRequirementGroups'] as $group) {
            foreach ($group['items'] as $item) {
                $entityTypes[] = $item['entity_type'];
            }
        }

        $this->assertNotContains('product_template', $entityTypes);
        $this->assertNotContains('product_template_version', $entityTypes);
    }

    public function test_merge_ui_selection_for_sync_preserves_hidden_template_settings(): void
    {
        $service = new AccountChangeRequestRequirementService();

        $merged = $service->mergeUiSelectionForSync(0, ['quote']);

        $this->assertContains('quote', $merged);
        $this->assertContains('product_template', $merged);
        $this->assertContains('product_template_version', $merged);
        $this->assertNotContains('account', $merged);
    }
}
