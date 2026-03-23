<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

class LowStockAlertService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listForBusiness(int $businessId, int $limit = 50): Collection
    {
        return Product::query()
            ->forBusiness($businessId)
            ->where('is_active', true)
            ->where('min_stock', '>', 0)
            ->whereColumn('stock', '<=', 'min_stock')
            ->orderByRaw('case when stock <= 0 then 0 else 1 end')
            ->orderBy('stock')
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'business_id', 'name', 'stock', 'min_stock'])
            ->map(function (Product $product): array {
                $stock = (float) $product->stock;
                $minStock = (float) $product->min_stock;
                $status = $stock <= 0 ? 'out_of_stock' : 'low_stock';

                return [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'stock' => $stock,
                    'min_stock' => $minStock,
                    'shortage' => max($minStock - $stock, 0),
                    'status' => $status,
                ];
            });
    }

    /**
     * @return array<string, int>
     */
    public function summarizeForBusiness(int $businessId): array
    {
        $products = Product::query()
            ->forBusiness($businessId)
            ->where('is_active', true)
            ->where('min_stock', '>', 0)
            ->whereColumn('stock', '<=', 'min_stock')
            ->get(['stock']);

        return [
            'total' => $products->count(),
            'out_of_stock' => $products->filter(fn (Product $product): bool => (float) $product->stock <= 0)->count(),
            'low_stock' => $products->filter(fn (Product $product): bool => (float) $product->stock > 0)->count(),
        ];
    }
}
