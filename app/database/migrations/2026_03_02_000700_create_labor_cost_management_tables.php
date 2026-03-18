<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labor_cost_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->decimal('hourly_rate', 12, 2)->default(9000);
            $table->text('memo')->nullable();
            $table->timestampsTz();
        });

        Schema::create('labor_processes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('process_code')->unique();
            $table->string('name');
            $table->decimal('default_yield_rate', 10, 6)->nullable();
            $table->boolean('active')->default(true)->index();
            $table->integer('sort_order')->default(0);
            $table->text('memo')->nullable();
            $table->timestampTz('deleted_at')->nullable()->index();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('labor_process_elements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('process_id')->constrained('labor_processes')->cascadeOnDelete();
            $table->string('element_code');
            $table->string('name');
            $table->decimal('work_minutes', 12, 6)->default(0);
            $table->decimal('activity_coeff', 10, 6)->default(1);
            $table->integer('batch_size')->default(1);
            $table->decimal('depreciation_amount', 12, 2)->default(0);
            $table->decimal('default_yield_rate', 10, 6)->nullable();
            $table->boolean('active')->default(true)->index();
            $table->integer('sort_order')->default(0);
            $table->text('memo')->nullable();
            $table->timestampTz('deleted_at')->nullable()->index();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['process_id', 'element_code']);
            $table->index(['process_id', 'sort_order']);
        });

        Schema::create('labor_auto_rules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('rule_code')->unique();
            $table->string('name');
            $table->foreignId('process_id')->constrained('labor_processes')->cascadeOnDelete();
            $table->integer('priority')->default(100)->index();
            $table->jsonb('include_tags_json')->default('[]');
            $table->jsonb('exclude_tags_json')->default('[]');
            $table->jsonb('required_sku_categories_json')->default('[]');
            $table->jsonb('required_sku_codes_json')->default('[]');
            $table->boolean('always_apply')->default(false)->index();
            $table->boolean('active')->default(true)->index();
            $table->text('memo')->nullable();
            $table->timestampTz('deleted_at')->nullable()->index();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['process_id']);
        });

        DB::table('labor_cost_settings')->insert([
            'id' => 1,
            'hourly_rate' => 9000,
            'memo' => 'default setting',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $processes = [
            ['process_code' => 'TEC20', 'name' => 'TEC20加工', 'sort_order' => 10],
            ['process_code' => 'TEC30', 'name' => 'TEC30加工', 'sort_order' => 20],
            ['process_code' => 'TEC20_HP', 'name' => '高精度TEC20加工', 'sort_order' => 30],
            ['process_code' => 'TEC30_HP', 'name' => '高精度TEC30加工', 'sort_order' => 40],
            ['process_code' => 'MFD', 'name' => 'MFD加工', 'sort_order' => 50],
            ['process_code' => 'FUSION', 'name' => '融着部加工', 'sort_order' => 60],
            ['process_code' => 'CONN_NORMAL', 'name' => '通常ファイバコネクタ加工', 'sort_order' => 70],
            ['process_code' => 'CONN_PM', 'name' => 'PMファイバコネクタ加工', 'sort_order' => 80],
            ['process_code' => 'CONN_PM_APC', 'name' => 'PMファイバAPCコネクタ加工', 'sort_order' => 90],
            ['process_code' => 'PACKAGING', 'name' => '梱包', 'sort_order' => 100],
        ];

        $processIdByCode = [];
        foreach ($processes as $row) {
            $id = (int)DB::table('labor_processes')->insertGetId([
                'process_code' => $row['process_code'],
                'name' => $row['name'],
                'default_yield_rate' => 0.95,
                'active' => true,
                'sort_order' => $row['sort_order'],
                'memo' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $processIdByCode[$row['process_code']] = $id;
        }

        $insertElement = function (
            string $processCode,
            string $elementCode,
            string $name,
            float $workMinutes,
            float $activityCoeff,
            int $batchSize,
            float $depreciationAmount,
            int $sortOrder
        ) use ($processIdByCode): void {
            DB::table('labor_process_elements')->insert([
                'process_id' => (int)$processIdByCode[$processCode],
                'element_code' => $elementCode,
                'name' => $name,
                'work_minutes' => $workMinutes,
                'activity_coeff' => $activityCoeff,
                'batch_size' => $batchSize,
                'depreciation_amount' => $depreciationAmount,
                'default_yield_rate' => 0.9,
                'active' => true,
                'sort_order' => $sortOrder,
                'memo' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        };

        $insertElement('TEC20', 'TEC20', 'TEC20作業', 27, 1.0, 2, 1000, 10);
        $insertElement('TEC30', 'TEC30', 'TEC30作業', 50, 1.0, 2, 1000, 10);
        $insertElement('TEC20_HP', 'TEC20_HP', '高精度TEC20作業', 60, 1.0, 1, 1000, 10);
        $insertElement('TEC30_HP', 'TEC30_HP', '高精度TEC30作業', 80, 1.0, 1, 1000, 10);
        $insertElement('MFD', 'MFD', 'MFD作業', 20, 1.0, 2, 1000, 10);
        $insertElement('FUSION', 'FUSION', '融着', 4, 2.5, 1, 1000, 10);
        $insertElement('FUSION', 'REINFORCE', '補強', 2, 2.5, 1, 1000, 20);

        $insertElement('CONN_NORMAL', 'ADHESIVE', '接着剤（消耗品）', 0, 0, 50, 600, 10);
        $insertElement('CONN_NORMAL', 'POLISH_SHEET', '研磨シート（消耗品）', 0, 0, 12, 1200, 20);
        $insertElement('CONN_NORMAL', 'CURE', '硬化', 72, 0.1, 20, 1000, 30);
        $insertElement('CONN_NORMAL', 'NORMAL_POLISH', '通常研磨', 30, 0.4, 10, 1000, 40);
        $insertElement('CONN_NORMAL', 'NORMAL_INSPECT', '通常検査', 2, 0.5, 1, 1000, 50);

        $insertElement('CONN_PM', 'ADHESIVE', '接着剤（消耗品）', 0, 0, 50, 600, 10);
        $insertElement('CONN_PM', 'POLISH_SHEET', '研磨シート（消耗品）', 0, 0, 12, 1200, 20);
        $insertElement('CONN_PM', 'CURE', '硬化', 72, 0.1, 20, 1000, 30);
        $insertElement('CONN_PM', 'PM_ALIGN', 'PM目合わせ', 30, 0.4, 10, 1000, 40);
        $insertElement('CONN_PM', 'PM_POLISH', 'PM研磨', 30, 0.4, 10, 1000, 50);
        $insertElement('CONN_PM', 'PM_INSPECT', 'PM検査', 6, 0.5, 1, 1000, 60);

        $insertElement('CONN_PM_APC', 'ADHESIVE', '接着剤（消耗品）', 0, 0, 50, 600, 10);
        $insertElement('CONN_PM_APC', 'POLISH_SHEET', '研磨シート（消耗品）', 0, 0, 12, 1200, 20);
        $insertElement('CONN_PM_APC', 'CURE', '硬化', 72, 0.1, 20, 1000, 30);
        $insertElement('CONN_PM_APC', 'PM_ALIGN', 'PM目合わせ', 30, 0.4, 10, 1000, 40);
        $insertElement('CONN_PM_APC', 'PM_APC_POLISH', 'PM/APC研磨', 30, 0.4, 10, 1000, 50);
        $insertElement('CONN_PM_APC', 'PM_APC_INSPECT', 'PM/APC検査', 6, 0.5, 1, 1000, 60);
        $insertElement('CONN_PM_APC', 'APC_SUPPORT', 'APC対応', 30, 0.4, 10, 1000, 70);

        $insertElement('PACKAGING', 'PACKAGING', '梱包', 20, 0.6, 50, 1000, 10);

        $insertRule = function (
            string $ruleCode,
            string $name,
            string $processCode,
            int $priority,
            array $includeTags = [],
            array $excludeTags = [],
            bool $alwaysApply = false
        ) use ($processIdByCode): void {
            DB::table('labor_auto_rules')->insert([
                'rule_code' => $ruleCode,
                'name' => $name,
                'process_id' => (int)$processIdByCode[$processCode],
                'priority' => $priority,
                'include_tags_json' => json_encode(array_values($includeTags), JSON_UNESCAPED_UNICODE),
                'exclude_tags_json' => json_encode(array_values($excludeTags), JSON_UNESCAPED_UNICODE),
                'required_sku_categories_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'required_sku_codes_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'always_apply' => $alwaysApply,
                'active' => true,
                'memo' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        };

        $insertRule('RULE_MFD', 'MFDタグでMFD工程を適用', 'MFD', 10, ['mfd']);
        $insertRule('RULE_TEC20', 'TEC20タグでTEC20工程を適用', 'TEC20', 20, ['tec20'], ['high_precision']);
        $insertRule('RULE_TEC30', 'TEC30タグでTEC30工程を適用', 'TEC30', 30, ['tec30'], ['high_precision']);
        $insertRule('RULE_TEC20_HP', 'TEC20+高精度タグで高精度TEC20工程を適用', 'TEC20_HP', 40, ['tec20', 'high_precision']);
        $insertRule('RULE_TEC30_HP', 'TEC30+高精度タグで高精度TEC30工程を適用', 'TEC30_HP', 50, ['tec30', 'high_precision']);
        $insertRule('RULE_FUSION', '融着タグで融着工程を適用', 'FUSION', 60, ['fusion']);
        $insertRule('RULE_CONN_NORMAL', 'コネクタ(通常)工程を適用', 'CONN_NORMAL', 70, ['connector'], ['pm']);
        $insertRule('RULE_CONN_PM', 'コネクタ(PM)工程を適用', 'CONN_PM', 80, ['connector', 'pm'], ['apc']);
        $insertRule('RULE_CONN_PM_APC', 'コネクタ(PM+APC)工程を適用', 'CONN_PM_APC', 90, ['connector', 'pm', 'apc']);
        $insertRule('RULE_PACKAGING', '梱包工程を常時適用', 'PACKAGING', 999, [], [], true);
    }

    public function down(): void
    {
        Schema::dropIfExists('labor_auto_rules');
        Schema::dropIfExists('labor_process_elements');
        Schema::dropIfExists('labor_processes');
        Schema::dropIfExists('labor_cost_settings');
    }
};
