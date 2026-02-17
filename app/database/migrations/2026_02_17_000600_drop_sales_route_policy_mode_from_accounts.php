<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('accounts', 'sales_route_policy_mode')) {
            Schema::table('accounts', function (Blueprint $table): void {
                $table->dropColumn('sales_route_policy_mode');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('accounts', 'sales_route_policy_mode')) {
            Schema::table('accounts', function (Blueprint $table): void {
                $table->enum('sales_route_policy_mode', ['legacy_allow_all', 'strict_allowlist'])
                    ->default('strict_allowlist');
            });
        }
    }
};
