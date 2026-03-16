<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->index(['id', 'business_id'], 'products_id_business_id_index');
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->index(['id', 'business_id'], 'sales_id_business_id_index');
        });

        Schema::table('purchases', function (Blueprint $table): void {
            $table->index(['id', 'business_id'], 'purchases_id_business_id_index');
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->foreign(['sale_id', 'business_id'], 'sale_items_sale_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('sales')
                ->cascadeOnDelete();

            $table->foreign(['product_id', 'business_id'], 'sale_items_product_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('products');
        });

        Schema::table('purchase_items', function (Blueprint $table): void {
            $table->foreign(['purchase_id', 'business_id'], 'purchase_items_purchase_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('purchases')
                ->cascadeOnDelete();

            $table->foreign(['product_id', 'business_id'], 'purchase_items_product_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('products');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table): void {
            $table->dropForeign('purchase_items_purchase_id_business_id_foreign');
            $table->dropForeign('purchase_items_product_id_business_id_foreign');
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropForeign('sale_items_sale_id_business_id_foreign');
            $table->dropForeign('sale_items_product_id_business_id_foreign');
        });

        Schema::table('purchases', function (Blueprint $table): void {
            $table->dropIndex('purchases_id_business_id_index');
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropIndex('sales_id_business_id_index');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_id_business_id_index');
        });
    }
};
