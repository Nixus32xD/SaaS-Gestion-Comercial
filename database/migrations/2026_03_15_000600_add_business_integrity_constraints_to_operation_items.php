<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->createUniqueIndexIfMissing('products', 'products_id_business_id_unique', function (Blueprint $table): void {
            $table->unique(['id', 'business_id'], 'products_id_business_id_unique');
        });

        $this->createUniqueIndexIfMissing('sales', 'sales_id_business_id_unique', function (Blueprint $table): void {
            $table->unique(['id', 'business_id'], 'sales_id_business_id_unique');
        });

        $this->createUniqueIndexIfMissing('purchases', 'purchases_id_business_id_unique', function (Blueprint $table): void {
            $table->unique(['id', 'business_id'], 'purchases_id_business_id_unique');
        });

        $this->createForeignIfMissing('sale_items', 'sale_items_sale_id_business_id_foreign', function (Blueprint $table): void {
            $table->foreign(['sale_id', 'business_id'], 'sale_items_sale_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('sales')
                ->cascadeOnDelete();
        });

        $this->createForeignIfMissing('sale_items', 'sale_items_product_id_business_id_foreign', function (Blueprint $table): void {
            $table->foreign(['product_id', 'business_id'], 'sale_items_product_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('products');
        });

        $this->createForeignIfMissing('purchase_items', 'purchase_items_purchase_id_business_id_foreign', function (Blueprint $table): void {
            $table->foreign(['purchase_id', 'business_id'], 'purchase_items_purchase_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('purchases')
                ->cascadeOnDelete();
        });

        $this->createForeignIfMissing('purchase_items', 'purchase_items_product_id_business_id_foreign', function (Blueprint $table): void {
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
        $this->dropForeignIfExists('purchase_items', 'purchase_items_purchase_id_business_id_foreign');
        $this->dropForeignIfExists('purchase_items', 'purchase_items_product_id_business_id_foreign');
        $this->dropForeignIfExists('sale_items', 'sale_items_sale_id_business_id_foreign');
        $this->dropForeignIfExists('sale_items', 'sale_items_product_id_business_id_foreign');

        $this->dropUniqueIfExists('purchases', 'purchases_id_business_id_unique');
        $this->dropUniqueIfExists('sales', 'sales_id_business_id_unique');
        $this->dropUniqueIfExists('products', 'products_id_business_id_unique');
    }

    private function createUniqueIndexIfMissing(string $table, string $indexName, \Closure $callback): void
    {
        if ($this->uniqueIndexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, $callback);
    }

    private function dropUniqueIfExists(string $table, string $indexName): void
    {
        if (! $this->uniqueIndexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($indexName): void {
            $table->dropUnique($indexName);
        });
    }

    private function createForeignIfMissing(string $table, string $foreignName, \Closure $callback): void
    {
        if ($this->foreignExists($table, $foreignName)) {
            return;
        }

        Schema::table($table, $callback);
    }

    private function dropForeignIfExists(string $table, string $foreignName): void
    {
        if (! $this->foreignExists($table, $foreignName)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($foreignName): void {
            $table->dropForeign($foreignName);
        });
    }

    private function uniqueIndexExists(string $table, string $indexName): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->where('non_unique', 0)
            ->exists();
    }

    private function foreignExists(string $table, string $foreignName): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('constraint_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('constraint_name', $foreignName)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }
};
