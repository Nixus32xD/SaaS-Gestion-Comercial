<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('global_products', function (Blueprint $table): void {
            if (! Schema::hasColumn('global_products', 'sku')) {
                $table->string('sku')->nullable()->after('barcode');
            }
        });

        Schema::table('global_products', function (Blueprint $table): void {
            $table->dropUnique('global_products_normalized_name_unique');
            $table->unique('sku');
            $table->index('normalized_name');
        });
    }

    public function down(): void
    {
        Schema::table('global_products', function (Blueprint $table): void {
            $table->dropIndex('global_products_normalized_name_index');
            $table->dropUnique('global_products_sku_unique');
            $table->unique('normalized_name');
        });

        Schema::table('global_products', function (Blueprint $table): void {
            if (Schema::hasColumn('global_products', 'sku')) {
                $table->dropColumn('sku');
            }
        });
    }
};
