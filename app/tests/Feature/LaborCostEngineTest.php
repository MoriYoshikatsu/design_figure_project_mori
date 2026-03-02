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
