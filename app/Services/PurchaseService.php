<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(private readonly DocumentNumberService $documentNumberService)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createPurchase(Business $business, User $user, array $payload): Purchase
    {
        return DB::transaction(function () use ($business, $user, $payload): Purchase {
            $purchasedAt = $this->resolveDateTimeValue($payload['purchased_at'] ?? null);

            $supplierId = data_get($payload, 'supplier_id');
            $supplier = null;

            if ($supplierId !== null) {
                $supplier = Supplier::query()
                    ->forBusiness($business->id)
                    ->whereKey((int) $supplierId)
                    ->first();

                if ($supplier === null) {
                    throw ValidationException::withMessages([
                        'supplier_id' => 'El proveedor no pertenece a este comercio.',
                    ]);
                }
            }

            $items = collect((array) ($payload['items'] ?? []));
            $existingProductIds = $items
                ->pluck('product_id')
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            /** @var Collection<int, Product> $products */
            $products = Product::query()
                ->forBusiness($business->id)
                ->whereIn('id', $existingProductIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== $existingProductIds->count()) {
                throw ValidationException::withMessages([
                    'items' => 'Hay productos invalidos para este comercio.',
                ]);
            }

            $lines = $items->map(function (array $item) use ($products, $business, $supplier, $purchasedAt): array {
                $quantity = round((float) $item['quantity'], 3);
                $unitCost = round((float) $item['unit_cost'], 2);
                $productId = data_get($item, 'product_id');

                if ($productId !== null && $productId !== '') {
                    $product = $products->get((int) $productId);

                    if ($product === null) {
                        throw ValidationException::withMessages([
                            'items' => 'Uno o mas productos no existen en el comercio.',
                        ]);
                    }
                } else {
                    $newName = trim((string) data_get($item, 'product.name'));
                    if ($newName === '') {
                        throw ValidationException::withMessages([
                            'items' => 'Los items sin producto deben incluir nombre de producto.',
                        ]);
                    }

                    $product = Product::query()->create([
                        'business_id' => $business->id,
                        'supplier_id' => $supplier?->id,
                        'name' => $newName,
                        'slug' => $this->buildUniqueSlug($business->id, $newName),
                        'description' => null,
                        'barcode' => trim((string) data_get($item, 'product.barcode')) ?: null,
                        'sku' => trim((string) data_get($item, 'product.sku')) ?: null,
                        'unit_type' => data_get($item, 'product.unit_type', 'unit'),
                        'sale_price' => round((float) data_get($item, 'product.sale_price', $unitCost), 2),
                        'cost_price' => $unitCost,
                        'stock' => 0,
                        'min_stock' => round((float) data_get($item, 'product.min_stock', 0), 3),
                        'shelf_life_days' => data_get($item, 'product.shelf_life_days'),
                        'expiry_alert_days' => data_get($item, 'product.expiry_alert_days', 15),
                        'is_active' => true,
                    ]);
                }

                $expiresAt = $this->resolveExpirationDate(
                    $item,
                    $product,
                    $purchasedAt,
                );

                return [
                    'product' => $product,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'subtotal' => round($quantity * $unitCost, 2),
                    'expires_at' => $expiresAt?->toDateString(),
                ];
            });

            $subtotal = round((float) $lines->sum('subtotal'), 2);

            $purchase = Purchase::query()->create([
                'business_id' => $business->id,
                'user_id' => $user->id,
                'supplier_id' => $supplier?->id,
                'purchase_number' => $this->documentNumberService->nextPurchaseNumber($business->id),
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'notes' => $payload['notes'] ?? null,
                'purchased_at' => $purchasedAt,
            ]);

            foreach ($lines as $line) {
                /** @var Product $product */
                $product = $line['product'];
                $before = round((float) $product->stock, 3);
                $after = round($before + (float) $line['quantity'], 3);

                $purchase->items()->create([
                    'business_id' => $business->id,
                    'product_id' => $product->id,
                    'product_name' => $line['product_name'],
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                    'subtotal' => $line['subtotal'],
                    'expires_at' => $line['expires_at'],
                ]);

                $product->stock = $after;
                $product->cost_price = $line['unit_cost'];
                $product->save();

                StockMovement::query()->create([
                    'business_id' => $business->id,
                    'product_id' => $product->id,
                    'type' => 'purchase',
                    'reference_type' => Purchase::class,
                    'reference_id' => $purchase->id,
                    'quantity' => $line['quantity'],
                    'stock_before' => $before,
                    'stock_after' => $after,
                    'notes' => "Compra {$purchase->purchase_number}",
                    'created_by' => $user->id,
                ]);
            }

            return $purchase->load(['items', 'supplier', 'user']);
        });
    }

    private function buildUniqueSlug(int $businessId, string $name): string
    {
        $base = Str::slug($name);
        $slug = $base === '' ? 'product' : $base;
        $candidate = $slug;
        $counter = 1;

        while (Product::query()->forBusiness($businessId)->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$counter;
            $counter++;
        }

        return $candidate;
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

    private function resolveExpirationDate(array $item, Product $product, mixed $purchasedAt): ?Carbon
    {
        $manualExpiration = data_get($item, 'expires_at');

        if (is_string($manualExpiration) && trim($manualExpiration) !== '') {
            return Carbon::parse($manualExpiration)->startOfDay();
        }

        if ($product->shelf_life_days === null || (int) $product->shelf_life_days <= 0) {
            return null;
        }

        return Carbon::parse($purchasedAt)
            ->startOfDay()
            ->addDays((int) $product->shelf_life_days);
    }
}
