<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_product_stocks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('stock', 12, 3)->default(0);
            $table->decimal('reserved_stock', 12, 3)->default(0);
            $table->decimal('min_stock', 12, 3)->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'product_id'], 'branch_product_stocks_branch_product_unique');
            $table->index(['business_id', 'branch_id'], 'branch_product_stocks_business_branch_index');
            $table->index(['business_id', 'product_id'], 'branch_product_stocks_business_product_index');

            $table->foreign(['branch_id', 'business_id'], 'branch_product_stocks_branch_business_foreign')
                ->references(['id', 'business_id'])
                ->on('branches')
                ->restrictOnDelete();

            $table->foreign(['product_id', 'business_id'], 'branch_product_stocks_product_business_foreign')
                ->references(['id', 'business_id'])
                ->on('products')
                ->restrictOnDelete();
        });

        $this->backfillLegacyStock();
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_product_stocks');
    }

    private function backfillLegacyStock(): void
    {
        /** @var array<int, int|null> $defaultBranchIds */
        $defaultBranchIds = [];

        DB::table('products')
            ->orderBy('id')
            ->select(['id', 'business_id', 'stock', 'reserved_stock', 'min_stock'])
            ->chunkById(500, function (Collection $products) use (&$defaultBranchIds): void {
                foreach ($products as $product) {
                    $businessId = (int) $product->business_id;
                    $branchId = $defaultBranchIds[$businessId] ??= DB::table('branches')
                        ->where('business_id', $businessId)
                        ->where('is_default', true)
                        ->value('id');

                    if ($branchId === null) {
                        continue;
                    }

                    DB::table('branch_product_stocks')->insertOrIgnore([
                        'business_id' => $businessId,
                        'branch_id' => $branchId,
                        'product_id' => $product->id,
                        'stock' => $product->stock ?? 0,
                        'reserved_stock' => $product->reserved_stock ?? 0,
                        'min_stock' => $product->min_stock ?? 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }, 'id');
    }
};
