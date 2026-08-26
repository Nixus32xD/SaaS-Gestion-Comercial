<?php

namespace App\Services;

use App\Models\Business;
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

            foreach ($this->quantitiesByProduct($items) as $productId => $quantity) {
                $product = $products->get($productId);

                if ($product === null || $product->availableStock() < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Stock insuficiente para {$product?->name}.",
                    ]);
                }

                $product->reserved_stock = round((float) $product->reserved_stock + $quantity, 3);
                $product->save();
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
            $stocks = $products->mapWithKeys(
                fn (Product $product): array => [$product->id => round((float) $product->stock, 3)]
            );

            foreach ($items as $item) {
                $product = $products->get($item->product_id);
                $before = (float) $stocks->get($item->product_id, 0);
                $after = round($before - (float) $item->quantity, 3);

                if ($product === null || $after < 0) {
                    throw ValidationException::withMessages([
                        'items' => "No hay stock disponible para confirmar {$item->product_name}.",
                    ]);
                }

                $stocks->put($item->product_id, $after);

                $this->productBatchService->consumeStock($sale->business, $product, (float) $item->quantity, [
                    'movement_type' => 'sale',
                    'reference_type' => SaleItem::class,
                    'reference_id' => $item->id,
                    'notes' => "Venta {$sale->sale_number} confirmada por Mercado Pago Point",
                    'created_by' => $sale->user_id,
                ]);

                StockMovement::query()->create([
                    'business_id' => $sale->business_id,
                    'product_id' => $product->id,
                    'type' => 'sale',
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'quantity' => -1 * (float) $item->quantity,
                    'stock_before' => $before,
                    'stock_after' => $after,
                    'notes' => "Venta {$sale->sale_number} confirmada por Mercado Pago Point",
                    'created_by' => $sale->user_id,
                ]);
            }

            foreach ($this->quantitiesByProduct($items) as $productId => $quantity) {
                $product = $products->get($productId);
                $product->stock = (float) $stocks->get($productId, 0);
                $product->reserved_stock = max(0, round((float) $product->reserved_stock - $quantity, 3));
                $product->save();
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

            foreach ($this->quantitiesByProduct($items) as $productId => $quantity) {
                $product = $products->get($productId);
                $product->reserved_stock = max(0, round((float) $product->reserved_stock - $quantity, 3));
                $product->save();
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
