<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleStockReservationService
{
    public function __construct(
        private readonly ProductBatchService $productBatchService,
        private readonly BranchProductStockService $branchProductStockService,
    ) {}

    public function reserve(Sale $sale): void
    {
        DB::transaction(function () use ($sale): void {
            $sale = $this->lockedSale($sale);

            if ($sale->stock_reservation_status === Sale::STOCK_RESERVATION_RESERVED
                || $sale->stock_reservation_status === Sale::STOCK_RESERVATION_CONSUMED) {
                return;
            }

            $items = $this->stockItems($sale);
            $products = $this->lockedProducts($sale, $items);
            $branch = $this->saleBranch($sale);

            foreach ($this->quantitiesByProduct($items) as $productId => $quantity) {
                $product = $products->get($productId);

                if ($product === null) {
                    throw ValidationException::withMessages([
                        'items' => "Stock insuficiente para {$product?->name}.",
                    ]);
                }

                $this->branchProductStockService->reserve($branch, $product, $quantity);
            }

            $sale->forceFill([
                'stock_reservation_status' => Sale::STOCK_RESERVATION_RESERVED,
            ])->save();
        });
    }

    public function consume(Sale $sale): void
    {
        DB::transaction(function () use ($sale): void {
            $sale = $this->lockedSale($sale);

            if ($sale->stock_reservation_status !== Sale::STOCK_RESERVATION_RESERVED) {
                return;
            }

            $items = $this->stockItems($sale);
            $products = $this->lockedProducts($sale, $items);
            $branch = $this->saleBranch($sale);

            foreach ($items as $item) {
                $product = $products->get($item->product_id);

                if ($product === null) {
                    throw ValidationException::withMessages([
                        'items' => "No hay stock disponible para confirmar {$item->product_name}.",
                    ]);
                }

                $before = (float) ($this->branchProductStockService->stock($branch, $product)?->stock ?? 0);

                $this->productBatchService->consumeStock($sale->business, $branch, $product, (float) $item->quantity, [
                    'movement_type' => 'sale',
                    'reference_type' => SaleItem::class,
                    'reference_id' => $item->id,
                    'notes' => "Venta {$sale->sale_number} confirmada por Mercado Pago Point",
                    'created_by' => $sale->user_id,
                ]);

                $stock = $this->branchProductStockService->consumeReserved($branch, $product, (float) $item->quantity);

                StockMovement::query()->create([
                    'business_id' => $sale->business_id,
                    'branch_id' => $sale->branch_id,
                    'product_id' => $product->id,
                    'type' => 'sale',
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'quantity' => -1 * (float) $item->quantity,
                    'stock_before' => $before,
                    'stock_after' => $stock->stock,
                    'notes' => "Venta {$sale->sale_number} confirmada por Mercado Pago Point",
                    'created_by' => $sale->user_id,
                ]);
            }

            $sale->forceFill([
                'stock_reservation_status' => Sale::STOCK_RESERVATION_CONSUMED,
            ])->save();
        });
    }

    public function release(Sale $sale): void
    {
        DB::transaction(function () use ($sale): void {
            $sale = $this->lockedSale($sale);

            if ($sale->stock_reservation_status !== Sale::STOCK_RESERVATION_RESERVED) {
                return;
            }

            $items = $this->stockItems($sale);
            $products = $this->lockedProducts($sale, $items);
            $branch = $this->saleBranch($sale);

            foreach ($this->quantitiesByProduct($items) as $productId => $quantity) {
                $product = $products->get($productId);
                if ($product !== null) {
                    $this->branchProductStockService->release($branch, $product, $quantity);
                }
            }

            $sale->forceFill([
                'stock_reservation_status' => Sale::STOCK_RESERVATION_RELEASED,
            ])->save();
        });
    }

    private function lockedSale(Sale $sale): Sale
    {
        return Sale::query()
            ->whereKey($sale->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function saleBranch(Sale $sale): Branch
    {
        $branch = Branch::query()
            ->forBusiness($sale->business_id)
            ->whereKey($sale->branch_id)
            ->first();

        return $branch ?? Branch::query()
            ->forBusiness($sale->business_id)
            ->where('is_default', true)
            ->firstOrFail();
    }

    /**
     * @return Collection<int, SaleItem>
     */
    private function stockItems(Sale $sale): Collection
    {
        return $sale->items()
            ->whereNotNull('product_id')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, SaleItem>  $items
     * @return Collection<int, Product>
     */
    private function lockedProducts(Sale $sale, Collection $items): Collection
    {
        $productIds = $items->pluck('product_id')->unique()->sort()->values();

        return $productIds->isEmpty()
            ? collect()
            : Product::query()
                ->forBusiness($sale->business_id)
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
    }

    /**
     * @param  Collection<int, SaleItem>  $items
     * @return Collection<int, float>
     */
    private function quantitiesByProduct(Collection $items): Collection
    {
        return $items
            ->groupBy('product_id')
            ->map(fn (Collection $rows): float => round((float) $rows->sum('quantity'), 3));
    }
}
