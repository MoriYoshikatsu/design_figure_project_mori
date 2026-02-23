<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processing_labor_costs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('sku_id')->constrained('skus');
            $table->decimal('labor_time_hours', 12, 6)->default(0);
            $table->decimal('hourly_rate', 12, 2)->default(9000);
            $table->decimal('activity_coeff', 10, 6)->default(1);
            $table->decimal('yield_rate', 10, 6)->default(1);
            $table->decimal('consumables_amount', 12, 2)->default(0);
            $table->decimal('packaging_amount', 12, 2)->default(0);
            $table->decimal('fixed_process_amount', 12, 2)->default(0);
            $table->boolean('active')->default(true)->index();
            $table->text('memo')->nullable();
            $table->timestampTz('deleted_at')->nullable()->index();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['sku_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processing_labor_costs');
    }
};
