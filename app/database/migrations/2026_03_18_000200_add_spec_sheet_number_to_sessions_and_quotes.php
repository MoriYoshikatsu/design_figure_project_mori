<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('configurator_sessions', 'spec_sheet_number')) {
            Schema::table('configurator_sessions', function (Blueprint $table) {
                $table->string('spec_sheet_number')->nullable()->after('status');
            });
        }

        if (!Schema::hasColumn('quotes', 'spec_sheet_number')) {
            Schema::table('quotes', function (Blueprint $table) {
                $table->string('spec_sheet_number')->nullable()->after('currency');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('quotes', 'spec_sheet_number')) {
            Schema::table('quotes', function (Blueprint $table) {
                $table->dropColumn('spec_sheet_number');
            });
        }

        if (Schema::hasColumn('configurator_sessions', 'spec_sheet_number')) {
            Schema::table('configurator_sessions', function (Blueprint $table) {
                $table->dropColumn('spec_sheet_number');
            });
        }
    }
};
