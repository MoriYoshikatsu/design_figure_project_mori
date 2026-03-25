<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('skus') && !Schema::hasTable('parts')) {
            DB::statement('ALTER TABLE skus RENAME TO parts');
        }

        if (Schema::hasTable('parts')) {
            $this->renameColumnIfExists('parts', 'sku_code', 'part_code');
        }

        $this->renameColumnIfExists('price_book_items', 'sku_id', 'part_id');
        $this->renameColumnIfExists('quote_items', 'sku_id', 'part_id');
        $this->renameColumnIfExists('processing_labor_costs', 'sku_id', 'part_id');
        $this->renameColumnIfExists('labor_auto_rules', 'required_sku_categories_json', 'required_part_categories_json');
        $this->renameColumnIfExists('labor_auto_rules', 'required_sku_codes_json', 'required_part_codes_json');
    }

    public function down(): void
    {
        $this->renameColumnIfExists('labor_auto_rules', 'required_part_codes_json', 'required_sku_codes_json');
        $this->renameColumnIfExists('labor_auto_rules', 'required_part_categories_json', 'required_sku_categories_json');
        $this->renameColumnIfExists('processing_labor_costs', 'part_id', 'sku_id');
        $this->renameColumnIfExists('quote_items', 'part_id', 'sku_id');
        $this->renameColumnIfExists('price_book_items', 'part_id', 'sku_id');

        if (Schema::hasTable('parts')) {
            $this->renameColumnIfExists('parts', 'part_code', 'sku_code');
        }

        if (Schema::hasTable('parts') && !Schema::hasTable('skus')) {
            DB::statement('ALTER TABLE parts RENAME TO skus');
        }
    }

    private function renameColumnIfExists(string $table, string $from, string $to): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }
        if (!Schema::hasColumn($table, $from) || Schema::hasColumn($table, $to)) {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE %s RENAME COLUMN %s TO %s',
            $table,
            $from,
            $to
        ));
    }
};
