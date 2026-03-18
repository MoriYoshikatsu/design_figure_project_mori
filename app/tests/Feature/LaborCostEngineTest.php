<?php

namespace Tests\Feature;

use App\Services\LaborCostEngine;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class LaborCostEngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareTables();
    }

    public function test_matches_all_rules_and_dedupes_processes(): void
    {
        DB::table('labor_cost_settings')->insert([
            'id' => 1,
            'hourly_rate' => 9000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $processId = (int)DB::table('labor_processes')->insertGetId([
            'process_code' => 'CONN_PM_APC',
            'name' => 'PM/APC',
            'default_yield_rate' => 0.95,
            'active' => true,
            'sort_order' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('labor_process_elements')->insert([
            'process_id' => $processId,
            'element_code' => 'PM_APC_POLISH',
            'name' => 'PM/APC研磨',
            'work_minutes' => 60,
            'activity_coeff' => 1,
            'batch_size' => 1,
            'depreciation_amount' => 0,
            'default_yield_rate' => 0.9,
            'active' => true,
            'sort_order' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('labor_auto_rules')->insert([
            [
                'rule_code' => 'RULE_A',
                'name' => 'strict',
                'process_id' => $processId,
                'priority' => 10,
                'include_tags_json' => json_encode(['connector', 'pm', 'apc'], JSON_UNESCAPED_UNICODE),
                'exclude_tags_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'required_sku_categories_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'required_sku_codes_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'always_apply' => false,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'rule_code' => 'RULE_B',
                'name' => 'broad',
                'process_id' => $processId,
                'priority' => 20,
                'include_tags_json' => json_encode(['connector'], JSON_UNESCAPED_UNICODE),
                'exclude_tags_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'required_sku_categories_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'required_sku_codes_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'always_apply' => false,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('skus')->insert([
            'sku_code' => 'CONN_A',
            'category' => 'CONNECTOR',
            'attributes' => json_encode(['process_tags' => ['connector', 'pm', 'apc']], JSON_UNESCAPED_UNICODE),
        ]);

        $result = app(LaborCostEngine::class)->calculate([
            [
                'sku_code' => 'CONN_A',
                'quantity' => 1,
                'sort_order' => 0,
            ],
        ], 10, []);

        $this->assertCount(2, $result['matched_labor_rules']);
        $this->assertCount(1, $result['matched_process_codes']);
        $this->assertSame('CONN_PM_APC', $result['matched_process_codes'][0]);
        $this->assertEqualsWithDelta(105270.0, (float)$result['labor_order_total'], 0.001);
    }

    public function test_process_override_by_actual_input_has_priority_over_element_override(): void
    {
        DB::table('labor_cost_settings')->insert([
            'id' => 1,
            'hourly_rate' => 9000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $processId = (int)DB::table('labor_processes')->insertGetId([
            'process_code' => 'MFD',
            'name' => 'MFD',
            'default_yield_rate' => 0.95,
            'active' => true,
            'sort_order' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('labor_process_elements')->insert([
            'process_id' => $processId,
            'element_code' => 'MFD',
            'name' => 'MFD加工',
            'work_minutes' => 20,
            'activity_coeff' => 1,
            'batch_size' => 2,
            'depreciation_amount' => 1000,
            'default_yield_rate' => 0.9,
            'active' => true,
            'sort_order' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('labor_auto_rules')->insert([
            'rule_code' => 'RULE_MFD',
            'name' => 'MFD',
            'process_id' => $processId,
            'priority' => 10,
            'include_tags_json' => json_encode(['mfd'], JSON_UNESCAPED_UNICODE),
            'exclude_tags_json' => json_encode([], JSON_UNESCAPED_UNICODE),
            'required_sku_categories_json' => json_encode([], JSON_UNESCAPED_UNICODE),
            'required_sku_codes_json' => json_encode([], JSON_UNESCAPED_UNICODE),
            'always_apply' => false,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('skus')->insert([
            'sku_code' => 'PROC_MFD',
            'category' => 'PROC',
            'attributes' => json_encode(['process_tags' => ['mfd']], JSON_UNESCAPED_UNICODE),
        ]);

        $result = app(LaborCostEngine::class)->calculate([
            [
                'sku_code' => 'PROC_MFD',
                'quantity' => 1,
                'sort_order' => 0,
            ],
        ], 10, [
            'processes' => [
                'MFD' => [
                    'order_qty' => 10,
                    'actual_input_qty' => 20,
                ],
            ],
            'elements' => [
                'MFD' => [
                    'MFD' => [
                        'yield_rate' => 0.95,
                    ],
                ],
            ],
        ]);

        $this->assertEqualsWithDelta(80000.0, (float)$result['labor_order_total'], 0.001);
        $this->assertSame('process_override', (string)$result['processes'][0]['elements'][0]['yield_source']);
    }

    public function test_invalid_yield_falls_back_to_one_and_ratio_requires_both_inputs(): void
    {
        DB::table('labor_cost_settings')->insert([
            'id' => 1,
            'hourly_rate' => 9000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $processId = (int)DB::table('labor_processes')->insertGetId([
            'process_code' => 'MFD',
            'name' => 'MFD',
            'default_yield_rate' => 0.95,
            'active' => true,
            'sort_order' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('labor_process_elements')->insert([
            'process_id' => $processId,
            'element_code' => 'MFD',
            'name' => 'MFD加工',
            'work_minutes' => 20,
            'activity_coeff' => 1,
            'batch_size' => 2,
            'depreciation_amount' => 1000,
            'default_yield_rate' => 0.9,
            'active' => true,
            'sort_order' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('labor_auto_rules')->insert([
            'rule_code' => 'RULE_MFD',
            'name' => 'MFD',
            'process_id' => $processId,
            'priority' => 10,
            'include_tags_json' => json_encode(['mfd'], JSON_UNESCAPED_UNICODE),
            'exclude_tags_json' => json_encode([], JSON_UNESCAPED_UNICODE),
            'required_sku_categories_json' => json_encode([], JSON_UNESCAPED_UNICODE),
            'required_sku_codes_json' => json_encode([], JSON_UNESCAPED_UNICODE),
            'always_apply' => false,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('skus')->insert([
            'sku_code' => 'PROC_MFD',
            'category' => 'PROC',
            'attributes' => json_encode(['process_tags' => ['mfd']], JSON_UNESCAPED_UNICODE),
        ]);

        $engine = app(LaborCostEngine::class);
        $resultRatioMissingOrder = $engine->calculate([
            [
                'sku_code' => 'PROC_MFD',
                'quantity' => 1,
                'sort_order' => 0,
            ],
        ], 10, [
            'elements' => [
                'MFD' => [
                    'MFD' => [
                        'actual_input_qty' => 20,
                    ],
                ],
            ],
        ]);

        $this->assertSame(
            'element_default',
            (string)$resultRatioMissingOrder['processes'][0]['elements'][0]['yield_source']
        );
        $this->assertEqualsWithDelta(
            0.9,
            (float)$resultRatioMissingOrder['processes'][0]['elements'][0]['yield_rate_applied'],
            0.000001
        );

        $resultInvalidYield = $engine->calculate([
            [
                'sku_code' => 'PROC_MFD',
                'quantity' => 1,
                'sort_order' => 0,
            ],
        ], 10, [
            'elements' => [
                'MFD' => [
                    'MFD' => [
                        'yield_rate' => 0,
                    ],
                ],
            ],
        ]);

        $this->assertSame(
            'yield_override',
            (string)$resultInvalidYield['processes'][0]['elements'][0]['yield_source']
        );
        $this->assertEqualsWithDelta(
            1.0,
            (float)$resultInvalidYield['processes'][0]['elements'][0]['yield_rate_applied'],
            0.000001
        );
    }

    public function test_infers_missing_tags_from_sku_code_and_attributes_for_rule_matching(): void
    {
        DB::table('labor_cost_settings')->insert([
            'id' => 1,
            'hourly_rate' => 9000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tecHpProcessId = (int)DB::table('labor_processes')->insertGetId([
            'process_code' => 'TEC20_HP',
            'name' => 'TEC20_HP',
            'default_yield_rate' => 0.95,
            'active' => true,
            'sort_order' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('labor_process_elements')->insert([
            'process_id' => $tecHpProcessId,
            'element_code' => 'TEC20_HP',
            'name' => 'TEC20_HP',
            'work_minutes' => 20,
            'activity_coeff' => 1,
            'batch_size' => 1,
            'depreciation_amount' => 0,
            'default_yield_rate' => 0.95,
            'active' => true,
            'sort_order' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $connPmApcProcessId = (int)DB::table('labor_processes')->insertGetId([
            'process_code' => 'CONN_PM_APC',
            'name' => 'CONN_PM_APC',
            'default_yield_rate' => 0.95,
            'active' => true,
            'sort_order' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('labor_process_elements')->insert([
            'process_id' => $connPmApcProcessId,
            'element_code' => 'PM_APC_POLISH',
            'name' => 'PM_APC_POLISH',
            'work_minutes' => 20,
            'activity_coeff' => 1,
            'batch_size' => 1,
            'depreciation_amount' => 0,
            'default_yield_rate' => 0.95,
            'active' => true,
            'sort_order' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('labor_auto_rules')->insert([
            [
                'rule_code' => 'RULE_TEC20_HP',
                'name' => 'TEC20_HP',
                'process_id' => $tecHpProcessId,
                'priority' => 10,
                'include_tags_json' => json_encode(['tec20', 'high_precision'], JSON_UNESCAPED_UNICODE),
                'exclude_tags_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'required_sku_categories_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'required_sku_codes_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'always_apply' => false,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'rule_code' => 'RULE_CONN_PM_APC',
                'name' => 'CONN_PM_APC',
                'process_id' => $connPmApcProcessId,
                'priority' => 20,
                'include_tags_json' => json_encode(['connector', 'pm', 'apc'], JSON_UNESCAPED_UNICODE),
                'exclude_tags_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'required_sku_categories_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'required_sku_codes_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'always_apply' => false,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // process_tags を持たないSKUでも、sku_code/category/polish から推論してルール一致させる
        DB::table('skus')->insert([
            [
                'sku_code' => 'PROC_TEC20_HP',
                'category' => 'PROC',
                'attributes' => json_encode([], JSON_UNESCAPED_UNICODE),
            ],
            [
                'sku_code' => 'FIBER_PMF',
                'category' => 'FIBER',
                'attributes' => json_encode([], JSON_UNESCAPED_UNICODE),
            ],
            [
                'sku_code' => 'CONN_SC_APC',
                'category' => 'CONNECTOR',
                'attributes' => json_encode(['polish' => 'APC'], JSON_UNESCAPED_UNICODE),
            ],
        ]);

        $result = app(LaborCostEngine::class)->calculate([
            ['sku_code' => 'PROC_TEC20_HP', 'quantity' => 1, 'sort_order' => 0],
            ['sku_code' => 'FIBER_PMF', 'quantity' => 1, 'sort_order' => 1],
            ['sku_code' => 'CONN_SC_APC', 'quantity' => 1, 'sort_order' => 2],
        ], 2, []);

        $matchedCodes = is_array($result['matched_process_codes'] ?? null) ? $result['matched_process_codes'] : [];
        $this->assertContains('TEC20_HP', $matchedCodes);
        $this->assertContains('CONN_PM_APC', $matchedCodes);
    }

    public function test_connector_rules_select_normal_pm_and_pm_apc_exclusively(): void
    {
        DB::table('labor_cost_settings')->insert([
            'id' => 1,
            'hourly_rate' => 9000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $normalProcessId = (int)DB::table('labor_processes')->insertGetId([
            'process_code' => 'CONN_NORMAL',
            'name' => 'CONN_NORMAL',
            'default_yield_rate' => 0.95,
            'active' => true,
            'sort_order' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $pmProcessId = (int)DB::table('labor_processes')->insertGetId([
            'process_code' => 'CONN_PM',
            'name' => 'CONN_PM',
            'default_yield_rate' => 0.95,
            'active' => true,
            'sort_order' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $pmApcProcessId = (int)DB::table('labor_processes')->insertGetId([
            'process_code' => 'CONN_PM_APC',
            'name' => 'CONN_PM_APC',
            'default_yield_rate' => 0.95,
            'active' => true,
            'sort_order' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([
            [$normalProcessId, 'NORMAL_POLISH', '通常研磨'],
            [$pmProcessId, 'PM_POLISH', 'PM研磨'],
            [$pmApcProcessId, 'PM_APC_POLISH', 'PM/APC研磨'],
        ] as [$processId, $elementCode, $name]) {
            DB::table('labor_process_elements')->insert([
                'process_id' => $processId,
                'element_code' => $elementCode,
                'name' => $name,
                'work_minutes' => 10,
                'activity_coeff' => 1,
                'batch_size' => 1,
                'depreciation_amount' => 0,
                'default_yield_rate' => 0.95,
                'active' => true,
                'sort_order' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('labor_auto_rules')->insert([
            [
                'rule_code' => 'RULE_CONN_NORMAL',
                'name' => 'CONN_NORMAL',
                'process_id' => $normalProcessId,
                'priority' => 70,
                'include_tags_json' => json_encode(['connector'], JSON_UNESCAPED_UNICODE),
                'exclude_tags_json' => json_encode(['pm'], JSON_UNESCAPED_UNICODE),
                'required_sku_categories_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'required_sku_codes_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'always_apply' => false,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'rule_code' => 'RULE_CONN_PM',
                'name' => 'CONN_PM',
                'process_id' => $pmProcessId,
                'priority' => 80,
                'include_tags_json' => json_encode(['connector', 'pm'], JSON_UNESCAPED_UNICODE),
                'exclude_tags_json' => json_encode(['apc'], JSON_UNESCAPED_UNICODE),
                'required_sku_categories_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'required_sku_codes_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'always_apply' => false,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'rule_code' => 'RULE_CONN_PM_APC',
                'name' => 'CONN_PM_APC',
                'process_id' => $pmApcProcessId,
                'priority' => 90,
                'include_tags_json' => json_encode(['connector', 'pm', 'apc'], JSON_UNESCAPED_UNICODE),
                'exclude_tags_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'required_sku_categories_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'required_sku_codes_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'always_apply' => false,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('skus')->insert([
            [
                'sku_code' => 'FIBER_SMF28E+',
                'category' => 'FIBER',
                'attributes' => json_encode([], JSON_UNESCAPED_UNICODE),
            ],
            [
                'sku_code' => 'FIBER_PMF',
                'category' => 'FIBER',
                'attributes' => json_encode([], JSON_UNESCAPED_UNICODE),
            ],
            [
                'sku_code' => 'CONN_SC_PC',
                'category' => 'CONNECTOR',
                'attributes' => json_encode(['polish' => 'PC'], JSON_UNESCAPED_UNICODE),
            ],
            [
                'sku_code' => 'CONN_SC_APC',
                'category' => 'CONNECTOR',
                'attributes' => json_encode(['polish' => 'APC'], JSON_UNESCAPED_UNICODE),
            ],
        ]);

        $engine = app(LaborCostEngine::class);

        $normal = $engine->calculate([
            ['sku_code' => 'FIBER_SMF28E+', 'quantity' => 1, 'sort_order' => 0],
            ['sku_code' => 'CONN_SC_PC', 'quantity' => 1, 'sort_order' => 1],
        ], 1, []);
        $normalApc = $engine->calculate([
            ['sku_code' => 'FIBER_SMF28E+', 'quantity' => 1, 'sort_order' => 0],
            ['sku_code' => 'CONN_SC_APC', 'quantity' => 1, 'sort_order' => 1],
        ], 1, []);
        $pm = $engine->calculate([
            ['sku_code' => 'FIBER_PMF', 'quantity' => 1, 'sort_order' => 0],
            ['sku_code' => 'CONN_SC_PC', 'quantity' => 1, 'sort_order' => 1],
        ], 1, []);
        $pmApc = $engine->calculate([
            ['sku_code' => 'FIBER_PMF', 'quantity' => 1, 'sort_order' => 0],
            ['sku_code' => 'CONN_SC_APC', 'quantity' => 1, 'sort_order' => 1],
        ], 1, []);

        $this->assertSame(['CONN_NORMAL'], array_values($normal['matched_process_codes'] ?? []));
        $this->assertSame(['CONN_NORMAL'], array_values($normalApc['matched_process_codes'] ?? []));
        $this->assertSame(['CONN_PM'], array_values($pm['matched_process_codes'] ?? []));
        $this->assertSame(['CONN_PM_APC'], array_values($pmApc['matched_process_codes'] ?? []));
    }

    public function test_infers_fusion_tag_from_sleeve_category(): void
    {
        DB::table('labor_cost_settings')->insert([
            'id' => 1,
            'hourly_rate' => 9000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $fusionProcessId = (int)DB::table('labor_processes')->insertGetId([
            'process_code' => 'FUSION',
            'name' => 'FUSION',
            'default_yield_rate' => 0.95,
            'active' => true,
            'sort_order' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('labor_process_elements')->insert([
            'process_id' => $fusionProcessId,
            'element_code' => 'FUSION',
            'name' => 'FUSION',
            'work_minutes' => 5,
            'activity_coeff' => 1,
            'batch_size' => 1,
            'depreciation_amount' => 0,
            'default_yield_rate' => 0.95,
            'active' => true,
            'sort_order' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('labor_auto_rules')->insert([
            'rule_code' => 'RULE_FUSION',
            'name' => 'FUSION',
            'process_id' => $fusionProcessId,
            'priority' => 10,
            'include_tags_json' => json_encode(['fusion'], JSON_UNESCAPED_UNICODE),
            'exclude_tags_json' => json_encode([], JSON_UNESCAPED_UNICODE),
            'required_sku_categories_json' => json_encode([], JSON_UNESCAPED_UNICODE),
            'required_sku_codes_json' => json_encode([], JSON_UNESCAPED_UNICODE),
            'always_apply' => false,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('skus')->insert([
            'sku_code' => 'SLEEVE_RECOTE',
            'category' => 'SLEEVE',
            'attributes' => json_encode([], JSON_UNESCAPED_UNICODE),
        ]);

        $result = app(LaborCostEngine::class)->calculate([
            ['sku_code' => 'SLEEVE_RECOTE', 'quantity' => 1, 'sort_order' => 0],
        ], 1, []);

        $matchedCodes = is_array($result['matched_process_codes'] ?? null) ? $result['matched_process_codes'] : [];
        $this->assertContains('FUSION', $matchedCodes);
    }

    public function test_required_sku_codes_are_primary_and_exact_code_tags_no_longer_match(): void
    {
        DB::table('labor_cost_settings')->insert([
            'id' => 1,
            'hourly_rate' => 9000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $codeProcessId = (int)DB::table('labor_processes')->insertGetId([
            'process_code' => 'CODE_MATCH',
            'name' => 'CODE_MATCH',
            'default_yield_rate' => 0.95,
            'active' => true,
            'sort_order' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $tagProcessId = (int)DB::table('labor_processes')->insertGetId([
            'process_code' => 'TAG_MATCH',
            'name' => 'TAG_MATCH',
            'default_yield_rate' => 0.95,
            'active' => true,
            'sort_order' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $categoryProcessId = (int)DB::table('labor_processes')->insertGetId([
            'process_code' => 'CATEGORY_MATCH',
            'name' => 'CATEGORY_MATCH',
            'default_yield_rate' => 0.95,
            'active' => true,
            'sort_order' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([$codeProcessId, $tagProcessId, $categoryProcessId] as $index => $processId) {
            DB::table('labor_process_elements')->insert([
                'process_id' => $processId,
                'element_code' => 'WORK_' . ($index + 1),
                'name' => 'WORK_' . ($index + 1),
                'work_minutes' => 5,
                'activity_coeff' => 1,
                'batch_size' => 1,
                'depreciation_amount' => 0,
                'default_yield_rate' => 0.95,
                'active' => true,
                'sort_order' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('labor_auto_rules')->insert([
            [
                'rule_code' => 'RULE_CODE_MATCH',
                'name' => 'CODE_MATCH',
                'process_id' => $codeProcessId,
                'priority' => 10,
                'include_tags_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'exclude_tags_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'required_sku_categories_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'required_sku_codes_json' => json_encode(['CONN_SC_APC'], JSON_UNESCAPED_UNICODE),
                'always_apply' => false,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'rule_code' => 'RULE_TAG_MATCH',
                'name' => 'TAG_MATCH',
                'process_id' => $tagProcessId,
                'priority' => 20,
                'include_tags_json' => json_encode(['conn_sc_apc'], JSON_UNESCAPED_UNICODE),
                'exclude_tags_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'required_sku_categories_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'required_sku_codes_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'always_apply' => false,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'rule_code' => 'RULE_CATEGORY_MATCH',
                'name' => 'CATEGORY_MATCH',
                'process_id' => $categoryProcessId,
                'priority' => 30,
                'include_tags_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'exclude_tags_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'required_sku_categories_json' => json_encode(['CONNECTOR'], JSON_UNESCAPED_UNICODE),
                'required_sku_codes_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'always_apply' => false,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('skus')->insert([
            'sku_code' => 'CONN_SC_APC',
            'category' => 'CONNECTOR',
            'attributes' => json_encode(['polish' => 'APC'], JSON_UNESCAPED_UNICODE),
        ]);

        $result = app(LaborCostEngine::class)->calculate([
            ['sku_code' => 'CONN_SC_APC', 'quantity' => 1, 'sort_order' => 0],
        ], 1, []);

        $this->assertSame(['CODE_MATCH'], array_values($result['matched_process_codes'] ?? []));
    }

    private function prepareTables(): void
    {
        Schema::dropIfExists('labor_auto_rules');
        Schema::dropIfExists('labor_process_elements');
        Schema::dropIfExists('labor_processes');
        Schema::dropIfExists('labor_cost_settings');
        Schema::dropIfExists('skus');

        Schema::create('skus', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('sku_code')->unique();
            $table->string('category');
            $table->text('attributes')->nullable();
        });

        Schema::create('labor_cost_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->decimal('hourly_rate', 12, 2)->default(9000);
            $table->text('memo')->nullable();
            $table->timestamps();
        });

        Schema::create('labor_processes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('process_code')->unique();
            $table->string('name');
            $table->decimal('default_yield_rate', 10, 6)->nullable();
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('labor_process_elements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('process_id');
            $table->string('element_code');
            $table->string('name');
            $table->decimal('work_minutes', 12, 6)->default(0);
            $table->decimal('activity_coeff', 10, 6)->default(1);
            $table->integer('batch_size')->default(1);
            $table->decimal('depreciation_amount', 12, 2)->default(0);
            $table->decimal('default_yield_rate', 10, 6)->nullable();
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('labor_auto_rules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('rule_code')->unique();
            $table->string('name');
            $table->unsignedBigInteger('process_id');
            $table->integer('priority')->default(100);
            $table->text('include_tags_json')->nullable();
            $table->text('exclude_tags_json')->nullable();
            $table->text('required_sku_categories_json')->nullable();
            $table->text('required_sku_codes_json')->nullable();
            $table->boolean('always_apply')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }
}
