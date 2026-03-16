<?php

namespace Tests\Feature;

use App\Services\PricingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PricingServicePriceBookFallbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareTables();
    }

    public function test_uses_latest_valid_price_book_when_standard_names_are_absent(): void
    {
        DB::table('accounts')->insert([
            'id' => 1,
            'account_type' => 'B2B',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $priceBookId = (int)DB::table('price_books')->insertGetId([
            'name' => 'MFD',
            'version' => 1,
            'currency' => 'JPY',
            'valid_from' => now()->subDay()->toDateString(),
            'valid_to' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $skuId = (int)DB::table('skus')->insertGetId([
            'sku_code' => 'FIBER_SMF28E+',
            'category' => 'FIBER',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('price_book_items')->insert([
            'price_book_id' => $priceBookId,
            'sku_id' => $skuId,
            'pricing_model' => 'FIXED',
            'unit_price' => 1200,
            'price_per_m' => null,
            'formula' => null,
            'min_qty' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(PricingService::class)->price(1, [
            [
                'sku_code' => 'FIBER_SMF28E+',
                'quantity' => 2,
                'options' => [],
                'source_path' => '$.fibers[0]',
                'sort_order' => 0,
            ],
        ]);

        $this->assertSame($priceBookId, (int)($result['price_book_id'] ?? 0));
        $this->assertSame('JPY', (string)($result['currency'] ?? ''));
        $this->assertFalse((bool)($result['items'][0]['missing_price'] ?? true));
        $this->assertEqualsWithDelta(2400.0, (float)($result['items'][0]['line_total'] ?? 0.0), 0.001);
    }

    private function prepareTables(): void
    {
        Schema::dropIfExists('price_book_items');
        Schema::dropIfExists('price_books');
        Schema::dropIfExists('skus');
        Schema::dropIfExists('accounts');

        Schema::create('accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('account_type')->nullable();
            $table->timestamps();
        });

        Schema::create('price_books', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->integer('version')->default(1);
            $table->string('currency')->default('JPY');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();
        });

        Schema::create('skus', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('sku_code')->unique();
            $table->string('category')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('price_book_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('price_book_id');
            $table->unsignedBigInteger('sku_id');
            $table->string('pricing_model')->nullable();
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->decimal('price_per_m', 12, 4)->nullable();
            $table->text('formula')->nullable();
            $table->decimal('min_qty', 12, 3)->nullable();
            $table->timestamps();
        });
    }
}

