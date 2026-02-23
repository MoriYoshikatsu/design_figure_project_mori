<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->integer('order_qty')->default(1)->after('currency');
            $table->decimal('fixed_cost', 12, 2)->nullable()->after('order_qty');
            $table->decimal('management_factor', 10, 6)->nullable()->after('fixed_cost');
            $table->decimal('qty_discount_factor', 10, 6)->nullable()->after('management_factor');
            $table->decimal('customer_factor', 10, 6)->nullable()->after('qty_discount_factor');
            $table->decimal('freight_amount', 12, 2)->nullable()->after('customer_factor');
            $table->decimal('manual_discount_amount', 12, 2)->default(0)->after('freight_amount');
            $table->enum('trade_scope', ['DOMESTIC', 'OVERSEAS'])->default('DOMESTIC')->after('manual_discount_amount');
            $table->decimal('tax_rate', 8, 6)->nullable()->after('trade_scope');
            $table->foreignId('pricing_policy_id')->nullable()->after('tax_rate')->constrained('quote_pricing_policies')->nullOnDelete();

            $table->index(['pricing_policy_id']);
            $table->index(['trade_scope']);
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pricing_policy_id');
            $table->dropIndex(['trade_scope']);

            $table->dropColumn([
                'order_qty',
                'fixed_cost',
                'management_factor',
                'qty_discount_factor',
                'customer_factor',
                'freight_amount',
                'manual_discount_amount',
                'trade_scope',
                'tax_rate',
            ]);
        });
    }
};
