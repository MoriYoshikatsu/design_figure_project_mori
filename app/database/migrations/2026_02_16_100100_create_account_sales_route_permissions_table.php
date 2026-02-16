<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_sales_route_permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('account_id');
            $table->string('http_method', 10);
            $table->string('uri_pattern', 255);
            $table->enum('source', ['checkbox', 'manual'])->default('manual');
            $table->boolean('active')->default(true);
            $table->text('memo')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestampsTz();

            $table->unique(['account_id', 'http_method', 'uri_pattern'], 'account_sales_route_permissions_unique');
            $table->index(['account_id', 'active'], 'account_sales_route_permissions_account_active_idx');

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_sales_route_permissions');
    }
};
