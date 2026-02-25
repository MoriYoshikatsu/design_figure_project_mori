<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->decimal('customer_factor_default', 10, 6)->default(1)->after('assignee_name');
            $table->enum('trade_scope_default', ['DOMESTIC', 'OVERSEAS'])->default('DOMESTIC')->after('customer_factor_default');

            $table->index(['trade_scope_default']);
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndex(['trade_scope_default']);
            $table->dropColumn(['customer_factor_default', 'trade_scope_default']);
        });
    }
};
