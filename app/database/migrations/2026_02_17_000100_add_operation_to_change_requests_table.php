<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('change_requests', function (Blueprint $table) {
            $table->string('operation', 16)->default('UPDATE')->after('entity_id');
            $table->index(['status', 'operation'], 'change_requests_status_operation_idx');
        });

        DB::table('change_requests')
            ->whereNull('operation')
            ->update(['operation' => 'UPDATE']);
    }

    public function down(): void
    {
        Schema::table('change_requests', function (Blueprint $table) {
            $table->dropIndex('change_requests_status_operation_idx');
            $table->dropColumn('operation');
        });
    }
};
