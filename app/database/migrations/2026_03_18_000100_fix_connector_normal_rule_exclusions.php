<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('labor_auto_rules')) {
            return;
        }

        DB::table('labor_auto_rules')
            ->where('rule_code', 'RULE_CONN_NORMAL')
            ->update([
                'exclude_tags_json' => json_encode(['pm'], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('labor_auto_rules')) {
            return;
        }

        DB::table('labor_auto_rules')
            ->where('rule_code', 'RULE_CONN_NORMAL')
            ->update([
                'exclude_tags_json' => json_encode(['pm', 'apc'], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }
};
