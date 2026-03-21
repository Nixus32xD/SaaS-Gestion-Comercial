<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('global_product_id')
                ->nullable()
                ->after('business_id')
                ->constrained('global_products')
                ->nullOnDelete();

            $table->index(['business_id', 'global_product_id'], 'products_business_global_product_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_business_global_product_index');
            $table->dropConstrainedForeignId('global_product_id');
        });
    }
};
