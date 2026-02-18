<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->enum('account_type', ['B2B', 'B2C'])->nullable();
            $table->string('internal_name')->nullable();
            $table->text('memo')->nullable();
            $table->string('assignee_name')->nullable();
            $table->timestampsTz();
        });

        Schema::create('account_user', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('user_id'); // Laravel標準usersテーブルを使う想定
            $table->enum('role', ['admin', 'sales', 'customer']);
            $table->text('memo')->nullable();
            $table->timestampsTz();

            $table->primary(['account_id', 'user_id']);
            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::create('skus', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('sku_code')->unique(); // 例: FIBER_A
            $table->string('name');
            $table->enum('category', ['PROC', 'SLEEVE', 'FIBER', 'TUBE', 'CONNECTOR']);
            $table->boolean('active')->default(true);
            $table->jsonb('attributes')->default('{}'); // SKU属性（研磨仕様などもここに）
            $table->text('memo')->nullable();
            $table->timestampsTz();

            $table->index('category');
        });

        Schema::create('price_books', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->integer('version');
            $table->string('currency')->default('JPY');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->text('memo')->nullable();
            $table->timestampsTz();

            $table->unique(['name', 'version']);
        });

        Schema::create('price_book_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('price_book_id');
            $table->unsignedBigInteger('sku_id');

            $table->enum('pricing_model', ['FIXED', 'PER_M', 'FORMULA']);
            $table->decimal('unit_price', 12, 2)->nullable();     // FIXED
            $table->decimal('price_per_m', 12, 6)->nullable();   // PER_M
            $table->jsonb('formula')->nullable();                 // FORMULA（JSON式）

            $table->decimal('min_qty', 12, 3)->default(1);
            $table->text('memo')->nullable();
            $table->timestampsTz();

            $table->foreign('price_book_id')->references('id')->on('price_books');
            $table->foreign('sku_id')->references('id')->on('skus');

            $table->index(['price_book_id']);
            $table->index(['sku_id']);
        });

        Schema::create('product_templates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('template_code')->unique(); // MFD_CONVERSION_FIBER
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->text('memo')->nullable();
            $table->timestampsTz();
        });

        Schema::create('product_template_versions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('template_id');
            $table->integer('version');
            $table->string('dsl_version'); // 0.2など
            $table->jsonb('dsl_json');
            $table->boolean('active')->default(true);
            $table->text('memo')->nullable();
            $table->timestampsTz();

            $table->foreign('template_id')->references('id')->on('product_templates');
            $table->unique(['template_id', 'version']);
        });

        Schema::create('configurator_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('template_version_id');

            $table->enum('status', ['DRAFT', 'LOCKED', 'QUOTED', 'EXPIRED']);
            $table->jsonb('config');  // ユーザー入力の正本（config）
            $table->jsonb('derived')->default('{}'); // fiberCount等
            $table->jsonb('validation_errors')->default('[]'); // エラー配列
            $table->text('memo')->nullable();
            $table->timestampsTz();

            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('template_version_id')->references('id')->on('product_template_versions');

            $table->index('account_id');
            $table->index('status');
        });

        Schema::create('quotes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('session_id');

            $table->enum('status', ['ISSUED', 'ORDERED', 'CANCELLED']);
            $table->string('currency')->default('JPY');

            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2);

            $table->jsonb('snapshot'); // テンプレ版、価格表、BOM、計算内訳など
            $table->text('memo')->nullable();
            $table->timestampsTz();

            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('session_id')->references('id')->on('configurator_sessions');

            $table->index('account_id');
        });

        Schema::create('quote_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('quote_id');
            $table->unsignedBigInteger('sku_id');

            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 12, 6);
            $table->decimal('line_total', 12, 2);

            $table->jsonb('options')->default('{}'); // lengthM, toleranceM 等
            $table->string('source_path')->nullable(); // $.fibers[1] など
            $table->integer('sort_order')->default(0);
            $table->text('memo')->nullable();
            $table->timestampsTz();

            $table->foreign('quote_id')->references('id')->on('quotes');
            $table->foreign('sku_id')->references('id')->on('skus');

            $table->index('quote_id');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('action');      // CREATE_SKU 等
            $table->string('entity_type'); // sku/quote/template 等
            $table->unsignedBigInteger('entity_id')->nullable();

            $table->jsonb('before_json')->nullable();
            $table->jsonb('after_json')->nullable();
            $table->text('memo')->nullable();
            $table->timestampsTz();

            $table->foreign('actor_user_id')->references('id')->on('users');
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('configurator_sessions');
        Schema::dropIfExists('product_template_versions');
        Schema::dropIfExists('product_templates');
        Schema::dropIfExists('price_book_items');
        Schema::dropIfExists('price_books');
        Schema::dropIfExists('skus');
        Schema::dropIfExists('account_user');
        Schema::dropIfExists('accounts');
    }
};
