<?php

namespace Tests\Feature;

use App\Services\QuoteCalculationEngine;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class QuoteCalculationEngineProcessTypeIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareTables();
    }

    public function test_tec20_proc_tag_matches_labor_rule(): void
    {
        DB::table('labor_cost_settings')->insert([
            'id' => 1,
            'hourly_rate' => 9000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $processId = (int)DB::table('labor_processes')->insertGetId([
            'process_code' => 'TEC20',
            'name' => 'TEC20加工',
            'default_yield_rate' => 0.95,
            'active' => true,
            'sort_order' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('labor_process_elements')->insert([
            'process_id' => $processId,
            'element_code' => 'TEC20',
            'name' => 'TEC20作業',
            'work_minutes' => 30,
            'activity_coeff' => 1,
            'batch_size' => 2,
            'depreciation_amount' => 0,
            'default_yield_rate' => 0.95,
            'active' => true,
            'sort_order' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('labor_auto_rules')->insert([
            'rule_code' => 'RULE_TEC20',
            'name' => 'TEC20',
            'process_id' => $processId,
            'priority' => 10,
            'include_tags_json' => json_encode(['tec20'], JSON_UNESCAPED_UNICODE),
            'exclude_tags_json' => json_encode([], JSON_UNESCAPED_UNICODE),
            'required_sku_categories_json' => json_encode([], JSON_UNESCAPED_UNICODE),
            'required_sku_codes_json' => json_encode([], JSON_UNESCAPED_UNICODE),
            'always_apply' => false,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('skus')->insert([
            'sku_code' => 'PROC_TEC20',
            'category' => 'PROC',
            'attributes' => json_encode(['process_tags' => ['tec20']], JSON_UNESCAPED_UNICODE),
        ]);

        $result = app(QuoteCalculationEngine::class)->calculate(0, [
            ['sku_code' => 'PROC_TEC20', 'quantity' => 1, 'sort_order' => 0],
        ], [
            ['sort_order' => 0, 'line_total' => 0],
        ], [
            'order_qty' => 10,
            'labor_overrides' => [
                'processes' => [],
                'elements' => [],
            ],
        ]);

        $step0 = $result['pricing_steps']['step0'] ?? [];
        $matched = is_array($step0['matched_labor_rules'] ?? null) ? $step0['matched_labor_rules'] : [];
        $matchedCodes = array_map(static fn(array $row): string => (string)($row['process_code'] ?? ''), $matched);

        $this->assertContains('TEC20', $matchedCodes);
        $this->assertGreaterThan(0, (float)($step0['labor_order_total'] ?? 0));
    }

    public function test_mfd_ratio_override_is_reflected_in_quote_calculation(): void
    {
        DB::table('labor_cost_settings')->insert([
            'id' => 1,
            'hourly_rate' => 9000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $processId = (int)DB::table('labor_processes')->insertGetId([
            'process_code' => 'MFD',
            'name' => 'MFD加工',
            'default_yield_rate' => 1,
            'active' => true,
            'sort_order' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('labor_process_elements')->insert([
            'process_id' => $processId,
            'element_code' => 'MFD',
            'name' => 'MFD作業',
            'work_minutes' => 60,
            'activity_coeff' => 1,
            'batch_size' => 10,
            'depreciation_amount' => 0,
            'default_yield_rate' => 1,
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
            'sku_code' => 'PROC_MFD_CONVERSION',
            'category' => 'PROC',
            'attributes' => json_encode(['process_tags' => ['mfd']], JSON_UNESCAPED_UNICODE),
        ]);

        $result = app(QuoteCalculationEngine::class)->calculate(0, [
            ['sku_code' => 'PROC_MFD_CONVERSION', 'quantity' => 2, 'sort_order' => 0],
        ], [
            ['sort_order' => 0, 'line_total' => 0],
        ], [
            'order_qty' => 10,
            'labor_overrides' => [
                'processes' => [
                    'MFD' => [
                        'order_qty' => 8,
                        'actual_input_qty' => 10,
                    ],
                ],
                'elements' => [],
            ],
        ]);

        $step0 = $result['pricing_steps']['step0'] ?? [];
        $this->assertEqualsWithDelta(14070.0, (float)($step0['labor_order_total'] ?? 0), 0.001);
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
