<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    /**
     * @param array<string, mixed> $payload
     */
    public function createSale(Business $business, User $user, array $payload): Sale
    {
        return DB::transaction(function () use ($business, $user, $payload): Sale {
            $items = collect((array) ($payload['items'] ?? []));
            $productIds = $items->pluck('product_id')->map(fn ($id) => (int) $id)->unique()->values();

            /** @var Collection<int, Product> $products */
            $products = Product::query()
                ->forBusiness($business->id)
                ->whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== $productIds->count()) {
                throw ValidationException::withMessages([
                    'items' => 'Hay productos invalidos para este comercio.',
                ]);
            }

            $lineItems = $items->map(function (array $item) use ($products): array {
                $productId = (int) $item['product_id'];
                $product = $products->get($productId);

                if ($product === null) {
                    throw ValidationException::withMessages([
                        'items' => 'Producto no encontrado para la venta.',
                    ]);
                }

                $quantity = round((float) $item['quantity'], 3);
                $unitPrice = array_key_exists('unit_price', $item)
                    ? round((float) $item['unit_price'], 2)
                    : round((float) $product->sale_price, 2);
                $subtotal = round($quantity * $unitPrice, 2);

                return [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ];
            });

            $requestedByProduct = $lineItems
                ->groupBy('product_id')
                ->map(fn (Collection $rows): float => round((float) $rows->sum('quantity'), 3));

            foreach ($requestedByProduct as $productId => $requestedQty) {
                $product = $products->get((int) $productId);
                if ($product === null) {
                    continue;
                }

                if ((float) $product->stock < $requestedQty) {
                    throw ValidationException::withMessages([
                        'items' => "Stock insuficiente para {$product->name}.",
                    ]);
                }
            }

            $subtotal = round((float) $lineItems->sum('subtotal'), 2);
            $discount = min(round((float) ($payload['discount'] ?? 0), 2), $subtotal);
            $total = round($subtotal - $discount, 2);

            $sale = Sale::query()->create([
                'business_id' => $business->id,
                'user_id' => $user->id,
                'sale_number' => $this->nextSaleNumber($business->id),
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'notes' => $payload['notes'] ?? null,
                'sold_at' => $this->resolveDateTimeValue($payload['sold_at'] ?? null),
            ]);

            $stocks = $products->mapWithKeys(
                fn (Product $product): array => [$product->id => round((float) $product->stock, 3)]
            );

            foreach ($lineItems as $lineItem) {
                $productId = (int) $lineItem['product_id'];
                $before = (float) $stocks->get($productId, 0);
                $after = round($before - (float) $lineItem['quantity'], 3);

                if ($after < 0) {
                    throw ValidationException::withMessages([
                        'items' => 'La venta deja stock negativo en uno o mas productos.',
                    ]);
                }

                $stocks->put($productId, $after);

                $sale->items()->create([
                    'product_id' => $productId,
                    'product_name' => $lineItem['product_name'],
                    'quantity' => $lineItem['quantity'],
                    'unit_price' => $lineItem['unit_price'],
                    'subtotal' => $lineItem['subtotal'],
                ]);

                StockMovement::query()->create([
                    'business_id' => $business->id,
                    'product_id' => $productId,
                    'type' => 'sale',
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'quantity' => -1 * (float) $lineItem['quantity'],
                    'stock_before' => $before,
                    'stock_after' => $after,
                    'notes' => "Venta {$sale->sale_number}",
                    'created_by' => $user->id,
                ]);
            }

            foreach ($products as $product) {
                $product->stock = (float) $stocks->get($product->id, 0);
                $product->save();
            }

            return $sale->load(['items', 'user']);
        });
    }

    private function nextSaleNumber(int $businessId): string
    {
        $lastId = Sale::query()
            ->forBusiness($businessId)
            ->max('id');

        return 'S-'.str_pad((string) ((int) $lastId + 1), 6, '0', STR_PAD_LEFT);
    }

    private function resolveDateTimeValue(mixed $value): mixed
    {
        if ($value === null) {
            return now();
        }

        if (is_string($value) && trim($value) === '') {
            return now();
        }

        return $value;
    }
}
