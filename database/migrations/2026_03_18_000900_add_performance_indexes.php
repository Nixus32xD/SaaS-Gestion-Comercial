<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->index(['business_id', 'is_active', 'name'], 'products_business_active_name_index');
        });

        Schema::table('purchase_items', function (Blueprint $table): void {
            $table->index(['business_id', 'expires_at'], 'purchase_items_business_expires_at_index');
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->index(['business_id', 'sale_sector_id', 'sold_at'], 'sales_business_sector_sold_at_index');
            $table->index(['business_id', 'payment_destination_id', 'sold_at'], 'sales_business_payment_destination_sold_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropIndex('sales_business_payment_destination_sold_at_index');
            $table->dropIndex('sales_business_sector_sold_at_index');
        });

        Schema::table('purchase_items', function (Blueprint $table): void {
            $table->dropIndex('purchase_items_business_expires_at_index');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_business_active_name_index');
        });
    }
};
