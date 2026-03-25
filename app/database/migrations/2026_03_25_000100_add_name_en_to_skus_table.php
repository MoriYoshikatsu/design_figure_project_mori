<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = Schema::hasTable('parts') ? 'parts' : (Schema::hasTable('skus') ? 'skus' : null);
        if ($tableName !== null && !Schema::hasColumn($tableName, 'name_en')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('name_en')->nullable();
            });
        }
    }

    public function down(): void
    {
        $tableName = Schema::hasTable('parts') ? 'parts' : (Schema::hasTable('skus') ? 'skus' : null);
        if ($tableName !== null && Schema::hasColumn($tableName, 'name_en')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('name_en');
            });
        }
    }
};
