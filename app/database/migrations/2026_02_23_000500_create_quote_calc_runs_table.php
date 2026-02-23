<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_calc_runs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('quote_id')->constrained('quotes')->cascadeOnDelete();
            $table->integer('run_no');
            $table->string('event_type', 64);
            $table->boolean('is_important')->default(false);
            $table->string('source_type', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('subtotal_raw', 16, 4)->nullable();
            $table->decimal('unit_price_rounded', 16, 4)->nullable();
            $table->decimal('recomputed_total', 16, 4)->nullable();
            $table->decimal('adjusted_total', 16, 4)->nullable();
            $table->decimal('tax_rate', 8, 6)->nullable();
            $table->decimal('tax_amount', 16, 4)->nullable();
            $table->decimal('grand_total', 16, 4)->nullable();
            $table->string('rounding_currency', 16)->nullable();
            $table->decimal('rounding_unit', 12, 4)->nullable();
            $table->string('rounding_mode', 32)->nullable();
            $table->timestampsTz();

            $table->unique(['quote_id', 'run_no']);
            $table->index(['quote_id', 'is_important', 'created_at']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_calc_runs');
    }
};
