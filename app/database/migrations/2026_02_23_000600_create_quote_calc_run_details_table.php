<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_calc_run_details', function (Blueprint $table) {
            $table->foreignId('run_id')->primary()->constrained('quote_calc_runs')->cascadeOnDelete();
            $table->jsonb('input_json')->default('{}');
            $table->jsonb('step_json')->default('{}');
            $table->jsonb('output_json')->default('{}');
            $table->jsonb('context_json')->default('{}');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_calc_run_details');
    }
};
