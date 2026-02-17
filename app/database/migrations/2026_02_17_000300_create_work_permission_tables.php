<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_permission_catalog', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('permission_key')->unique();
            $table->string('http_method', 10);
            $table->string('uri_pattern', 255);
            $table->string('label')->nullable();
            $table->enum('default_scope', ['global', 'account'])->default('global');
            $table->boolean('active')->default(true);
            $table->timestampsTz();

            $table->index(['http_method', 'uri_pattern'], 'work_permission_catalog_method_uri_idx');
        });

        Schema::create('work_permission_grants', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('permission_catalog_id');
            $table->enum('effect', ['allow', 'deny'])->default('allow');
            $table->enum('scope_type', ['global', 'account'])->default('global');
            $table->unsignedBigInteger('account_id')->nullable();
            $table->boolean('active')->default(true);
            $table->text('memo')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestampsTz();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('permission_catalog_id')->references('id')->on('work_permission_catalog')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->unique(['user_id', 'permission_catalog_id', 'effect', 'scope_type', 'account_id'], 'work_permission_grants_unique');
            $table->index(['user_id', 'active'], 'work_permission_grants_user_active_idx');
            $table->index(['scope_type', 'account_id'], 'work_permission_grants_scope_account_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_permission_grants');
        Schema::dropIfExists('work_permission_catalog');
    }
};
