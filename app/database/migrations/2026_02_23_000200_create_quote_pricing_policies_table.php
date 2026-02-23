<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_pricing_policies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->decimal('fixed_cost_default', 12, 2)->default(6000);
            $table->jsonb('management_tiers_json')->default('[]');
            $table->jsonb('quantity_discount_tiers_json')->default('[]');
            $table->decimal('domestic_freight_default', 12, 2)->default(3000);
            $table->decimal('domestic_tax_rate', 8, 6)->default(0.10);
            $table->decimal('overseas_tax_rate', 8, 6)->default(0.00);
            $table->jsonb('rounding_rules_json')->default('{}');
            $table->date('active_from')->nullable()->index();
            $table->date('active_to')->nullable()->index();
            $table->timestampsTz();
        });

        DB::table('quote_pricing_policies')->insert([
            'fixed_cost_default' => 6000,
            'management_tiers_json' => json_encode([
                ['min' => 0, 'max' => 49, 'factor' => 1.20],
                ['min' => 50, 'max' => 199, 'factor' => 1.15],
                ['min' => 200, 'max' => null, 'factor' => 1.13],
            ], JSON_UNESCAPED_UNICODE),
            'quantity_discount_tiers_json' => json_encode([
                ['min' => 0, 'max' => 99, 'factor' => 1.00],
                ['min' => 100, 'max' => 299, 'factor' => 0.98],
                ['min' => 300, 'max' => null, 'factor' => 0.95],
            ], JSON_UNESCAPED_UNICODE),
            'domestic_freight_default' => 3000,
            'domestic_tax_rate' => 0.10,
            'overseas_tax_rate' => 0.00,
            'rounding_rules_json' => json_encode([
                'JPY' => ['unit' => 100, 'mode' => 'ROUNDUP'],
                'DEFAULT' => ['unit' => 1, 'mode' => 'ROUNDUP'],
            ], JSON_UNESCAPED_UNICODE),
            'active_from' => now()->toDateString(),
            'active_to' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_pricing_policies');
    }
};
