<?php

namespace App\Services;

use App\Models\BranchProductStock;
use Illuminate\Support\Collection;

class LowStockAlertService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listForBusiness(int $businessId, int $limit = 50, ?int $branchId = null): Collection
    {
        return $this->alertQuery($businessId, $branchId)
            ->orderByRaw('case when branch_product_stocks.stock - branch_product_stocks.reserved_stock <= 0 then 0 else 1 end')
            ->orderByRaw('branch_product_stocks.stock - branch_product_stocks.reserved_stock')
            ->orderBy('products.name')
            ->orderBy('branches.name')
            ->limit($limit)
            ->get([
                'branch_product_stocks.id', 'branch_product_stocks.branch_id', 'branch_product_stocks.product_id',
                'branch_product_stocks.stock', 'branch_product_stocks.reserved_stock', 'branch_product_stocks.min_stock',
                'products.name as product_name', 'branches.name as branch_name',
            ])
            ->map(function (BranchProductStock $stock): array {
                $physicalStock = (float) $stock->stock;
                $reservedStock = (float) $stock->reserved_stock;
                $availableStock = $stock->availableStock();
                $minStock = (float) $stock->min_stock;
                $status = $availableStock <= 0 ? 'out_of_stock' : 'low_stock';

                return [
                    'branch_product_stock_id' => $stock->id,
                    'branch_id' => $stock->branch_id,
                    'branch_name' => $stock->branch_name,
                    'product_id' => $stock->product_id,
                    'product_name' => $stock->product_name,
                    'stock' => $physicalStock,
                    'reserved_stock' => $reservedStock,
                    'available_stock' => $availableStock,
                    'min_stock' => $minStock,
                    'shortage' => max($minStock - $availableStock, 0),
                    'status' => $status,
                ];
            });
    }

    /**
     * @return array<string, int>
     */
    public function summarizeForBusiness(int $businessId): array
    {
        $stocks = $this->alertQuery($businessId)
            ->get(['branch_product_stocks.stock', 'branch_product_stocks.reserved_stock']);

        return [
            'total' => $stocks->count(),
            'out_of_stock' => $stocks->filter(fn (BranchProductStock $stock): bool => $stock->availableStock() <= 0)->count(),
            'low_stock' => $stocks->filter(fn (BranchProductStock $stock): bool => $stock->availableStock() > 0)->count(),
        ];
    }

    private function alertQuery(int $businessId, ?int $branchId = null)
    {
        return BranchProductStock::query()
            ->forBusiness($businessId)
            ->join('products', function ($join) use ($businessId): void {
                $join->on('products.id', '=', 'branch_product_stocks.product_id')
                    ->where('products.business_id', $businessId);
            })
            ->join('branches', function ($join) use ($businessId): void {
                $join->on('branches.id', '=', 'branch_product_stocks.branch_id')
                    ->where('branches.business_id', $businessId);
            })
            ->when($branchId !== null, fn ($query) => $query->where('branch_product_stocks.branch_id', $branchId))
            ->where('products.is_active', true)
            ->where('branches.is_active', true)
            ->where('branch_product_stocks.min_stock', '>', 0)
            ->whereRaw('branch_product_stocks.stock - branch_product_stocks.reserved_stock <= branch_product_stocks.min_stock');
    }
}
