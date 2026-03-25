<?php

namespace Tests\Feature;

use App\Services\QuoteCalculationEngine;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class QuoteCalculationEngineLaborIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareTables();
    }

    public function test_proc_price_lines_are_not_counted_as_parts_and_labor_is_computed_from_rules(): void
    {
        DB::table('labor_cost_settings')->insert([
            'id' => 1,
            'hourly_rate' => 9000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $processId = (int)DB::table('labor_processes')->insertGetId([
            'process_code' => 'PACKAGING',
            'name' => '梱包',
            'default_yield_rate' => 0.95,
            'active' => true,
            'sort_order' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('labor_process_elements')->insert([
            'process_id' => $processId,
            'element_code' => 'PACKAGING',
            'name' => '梱包',
            'work_minutes' => 20,
            'activity_coeff' => 0.6,
            'batch_size' => 50,
            'depreciation_amount' => 1000,
            'default_yield_rate' => 0.9,
            'active' => true,
            'sort_order' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('labor_auto_rules')->insert([
            'rule_code' => 'RULE_PACKAGING',
            'name' => 'always',
            'process_id' => $processId,
            'priority' => 100,
            'include_tags_json' => json_encode([], JSON_UNESCAPED_UNICODE),
            'exclude_tags_json' => json_encode([], JSON_UNESCAPED_UNICODE),
            'required_part_categories_json' => json_encode([], JSON_UNESCAPED_UNICODE),
            'required_part_codes_json' => json_encode([], JSON_UNESCAPED_UNICODE),
            'always_apply' => true,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('parts')->insert([
            [
                'part_code' => 'PART_A',
                'category' => 'SLEEVE',
                'attributes' => json_encode([], JSON_UNESCAPED_UNICODE),
            ],
            [
                'part_code' => 'PROC_MFD',
                'category' => 'PROC',
                'attributes' => json_encode(['process_tags' => ['mfd']], JSON_UNESCAPED_UNICODE),
            ],
        ]);

        DB::table('processing_labor_costs')->insert([
            'part_id' => 1,
            'unit_labor_cost' => 999999,
        ]);

        $engine = app(QuoteCalculationEngine::class);
        $result = $engine->calculate(0, [
            ['part_code' => 'PART_A', 'quantity' => 1, 'sort_order' => 0],
            ['part_code' => 'PROC_MFD', 'quantity' => 1, 'sort_order' => 1],
        ], [
            ['sort_order' => 0, 'line_total' => 1000],
            ['sort_order' => 1, 'line_total' => 9999],
        ], [
            'order_qty' => 5,
            'labor_overrides' => [
                'processes' => [],
                'elements' => [],
            ],
        ]);

        $step0 = $result['pricing_steps']['step0'] ?? [];
        $this->assertEqualsWithDelta(1000.0, (float)($step0['parts_unit_cost'] ?? 0), 0.001);
        $this->assertEqualsWithDelta(656.0, (float)($step0['labor_unit_cost'] ?? 0), 0.001);
        $this->assertEqualsWithDelta(3280.0, (float)($step0['labor_order_total'] ?? 0), 0.001);

        $pricingInput = $result['pricing_input'] ?? [];
        $this->assertArrayHasKey('labor_overrides', $pricingInput);
        $this->assertIsArray($pricingInput['labor_overrides']);
    }

    private function prepareTables(): void
    {
        Schema::dropIfExists('labor_auto_rules');
        Schema::dropIfExists('labor_process_elements');
        Schema::dropIfExists('labor_processes');
        Schema::dropIfExists('labor_cost_settings');
        Schema::dropIfExists('processing_labor_costs');
        Schema::dropIfExists('parts');

        Schema::create('parts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('part_code')->unique();
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
            $table->text('required_part_categories_json')->nullable();
            $table->text('required_part_codes_json')->nullable();
            $table->boolean('always_apply')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('processing_labor_costs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('part_id');
            $table->decimal('unit_labor_cost', 12, 2)->default(0);
        });
    }
}
