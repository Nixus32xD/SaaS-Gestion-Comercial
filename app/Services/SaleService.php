<?php

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessPaymentDestination;
use App\Models\BusinessSaleSector;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\ProductMeasurement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(
        private readonly DocumentNumberService $documentNumberService,
        private readonly ProductBatchService $productBatchService
    ) {}

    /**
     * @param  array<string, mixed>  $payload
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
                ->orderBy('id')
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
                $subtotal = ProductMeasurement::calculateSubtotal(
                    $quantity,
                    $unitPrice,
                    $product->unit_type,
                    $product->weight_unit
                );

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
            $paymentMethod = $this->resolvePaymentMethod($payload['payment_method'] ?? null);
            [$amountReceived, $changeAmount] = $this->resolvePaymentAmounts(
                $paymentMethod,
                $payload['amount_received'] ?? null,
                $total
            );
            [$saleSectorId, $paymentDestinationId] = $this->resolveAdvancedSaleContext($business, $payload);

            $sale = Sale::query()->create([
                'business_id' => $business->id,
                'user_id' => $user->id,
                'sale_sector_id' => $saleSectorId,
                'sale_number' => $this->documentNumberService->nextSaleNumber($business->id),
                'payment_method' => $paymentMethod,
                'payment_destination_id' => $paymentDestinationId,
                'amount_received' => $amountReceived,
                'change_amount' => $changeAmount,
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

                /** @var SaleItem $saleItem */
                $saleItem = $sale->items()->create([
                    'business_id' => $business->id,
                    'product_id' => $productId,
                    'product_name' => $lineItem['product_name'],
                    'quantity' => $lineItem['quantity'],
                    'unit_price' => $lineItem['unit_price'],
                    'subtotal' => $lineItem['subtotal'],
                ]);

                $this->productBatchService->consumeStock($business, $products->get($productId), (float) $lineItem['quantity'], [
                    'movement_type' => 'sale',
                    'reference_type' => SaleItem::class,
                    'reference_id' => $saleItem->id,
                    'notes' => "Venta {$sale->sale_number}",
                    'created_by' => $user->id,
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

            return $sale->load(['items', 'user', 'saleSector', 'paymentDestination']);
        });
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

    private function resolvePaymentMethod(mixed $value): string
    {
        return $value === 'transfer' ? 'transfer' : 'cash';
    }

    /**
     * @return array{0: float|null, 1: float|null}
     */
    private function resolvePaymentAmounts(string $paymentMethod, mixed $amountReceivedValue, float $total): array
    {
        if ($paymentMethod !== 'cash') {
            return [null, null];
        }

        $amountReceived = $amountReceivedValue === null || $amountReceivedValue === ''
            ? $total
            : round((float) $amountReceivedValue, 2);

        if ($amountReceived < $total) {
            throw ValidationException::withMessages([
                'amount_received' => 'El monto recibido no puede ser menor al total de la venta.',
            ]);
        }

        return [$amountReceived, round($amountReceived - $total, 2)];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: int|null, 1: int|null}
     */
    private function resolveAdvancedSaleContext(Business $business, array $payload): array
    {
        if (! $business->hasAdvancedSaleSettings()) {
            return [null, null];
        }

        $saleSectorId = (int) ($payload['sale_sector_id'] ?? 0);
        $paymentDestinationId = (int) ($payload['payment_destination_id'] ?? 0);

        $saleSectorExists = BusinessSaleSector::query()
            ->forBusiness($business->id)
            ->whereKey($saleSectorId)
            ->where('is_active', true)
            ->exists();

        if (! $saleSectorExists) {
            throw ValidationException::withMessages([
                'sale_sector_id' => 'El sector seleccionado no esta disponible para este comercio.',
            ]);
        }

        $paymentDestinationExists = BusinessPaymentDestination::query()
            ->forBusiness($business->id)
            ->whereKey($paymentDestinationId)
            ->where('is_active', true)
            ->exists();

        if (! $paymentDestinationExists) {
            throw ValidationException::withMessages([
                'payment_destination_id' => 'La cuenta seleccionada no esta disponible para este comercio.',
            ]);
        }

        return [$saleSectorId, $paymentDestinationId];
    }
}
