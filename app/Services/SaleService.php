<?php

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessPaymentDestination;
use App\Models\BusinessSaleSector;
use App\Models\Customer;
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
        private readonly ProductBatchService $productBatchService,
        private readonly CustomerAccountService $customerAccountService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createSale(Business $business, User $user, array $payload): Sale
    {
        return DB::transaction(function () use ($business, $user, $payload): Sale {
            $items = collect((array) ($payload['items'] ?? []));
            $productItems = $items
                ->filter(fn (array $item): bool => (int) ($item['product_id'] ?? 0) > 0)
                ->values();
            $productIds = $productItems->pluck('product_id')->map(fn ($id) => (int) $id)->unique()->values();

            /** @var Collection<int, Product> $products */
            $products = $productIds->isEmpty()
                ? collect()
                : Product::query()
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
                $productId = (int) ($item['product_id'] ?? 0);

                if ($productId <= 0) {
                    $quantity = 1.0;
                    $unitPrice = round((float) ($item['unit_price'] ?? 0), 2);

                    return [
                        'product_id' => null,
                        'product_name' => trim((string) ($item['product_name'] ?? 'Item manual')),
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'subtotal' => ProductMeasurement::calculateSubtotal($quantity, $unitPrice, null, null),
                        'affects_stock' => false,
                    ];
                }

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
                    'affects_stock' => true,
                ];
            });

            $requestedByProduct = $lineItems
                ->filter(fn (array $lineItem): bool => $lineItem['affects_stock'] === true)
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
            $customer = $this->resolveCustomer($business, $payload['customer_id'] ?? null);
            [$paymentStatus, $paymentMethod, $paidAmount, $pendingAmount, $amountReceived, $changeAmount] = $this->resolvePaymentData(
                $payload,
                $total,
                $customer
            );
            [$saleSectorId, $paymentDestinationId] = $this->resolveAdvancedSaleContext($business, $payload, $paidAmount);

            $sale = Sale::query()->create([
                'business_id' => $business->id,
                'user_id' => $user->id,
                'sale_sector_id' => $saleSectorId,
                'customer_id' => $customer?->id,
                'sale_number' => $this->documentNumberService->nextSaleNumber($business->id),
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'payment_destination_id' => $paymentDestinationId,
                'amount_received' => $amountReceived,
                'change_amount' => $changeAmount,
                'paid_amount' => $paidAmount,
                'pending_amount' => $pendingAmount,
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
                /** @var SaleItem $saleItem */
                $saleItem = $sale->items()->create([
                    'business_id' => $business->id,
                    'product_id' => $lineItem['product_id'],
                    'product_name' => $lineItem['product_name'],
                    'quantity' => $lineItem['quantity'],
                    'unit_price' => $lineItem['unit_price'],
                    'subtotal' => $lineItem['subtotal'],
                ]);

                if ($lineItem['affects_stock'] !== true) {
                    continue;
                }

                $productId = (int) $lineItem['product_id'];
                $before = (float) $stocks->get($productId, 0);
                $after = round($before - (float) $lineItem['quantity'], 3);

                if ($after < 0) {
                    throw ValidationException::withMessages([
                        'items' => 'La venta deja stock negativo en uno o mas productos.',
                    ]);
                }

                $stocks->put($productId, $after);

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

            if ($pendingAmount > 0 && $customer !== null) {
                $this->customerAccountService->recordDebtForSale(
                    $business,
                    $sale,
                    $customer,
                    $user,
                    $pendingAmount
                );
            }

            return $sale->load(['items', 'user', 'saleSector', 'paymentDestination', 'customer']);
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

    private function resolvePaymentMethod(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value === 'transfer' ? 'transfer' : 'cash';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: string|null, 2: float, 3: float, 4: float|null, 5: float|null}
     */
    private function resolvePaymentData(array $payload, float $total, ?Customer $customer): array
    {
        $paymentStatus = $this->resolvePaymentStatus($payload['payment_status'] ?? null);
        $paymentMethod = $this->resolvePaymentMethod($payload['payment_method'] ?? null);

        return match ($paymentStatus) {
            Sale::PAYMENT_STATUS_PENDING => $this->resolvePendingPaymentData($customer, $total),
            Sale::PAYMENT_STATUS_PARTIAL => $this->resolvePartialPaymentData($payload, $total, $paymentMethod, $customer),
            default => $this->resolvePaidPaymentData($payload, $total, $paymentMethod),
        };
    }

    private function resolvePaymentStatus(mixed $value): string
    {
        return match ($value) {
            Sale::PAYMENT_STATUS_PARTIAL => Sale::PAYMENT_STATUS_PARTIAL,
            Sale::PAYMENT_STATUS_PENDING => Sale::PAYMENT_STATUS_PENDING,
            default => Sale::PAYMENT_STATUS_PAID,
        };
    }

    /**
     * @return array{0: string, 1: string|null, 2: float, 3: float, 4: float|null, 5: float|null}
     */
    private function resolvePaidPaymentData(array $payload, float $total, ?string $paymentMethod): array
    {
        if ($paymentMethod === null) {
            throw ValidationException::withMessages([
                'payment_method' => 'Debes seleccionar un medio de pago para ventas pagadas.',
            ]);
        }

        [$amountReceived, $changeAmount] = $this->resolveCollectedAmounts(
            $paymentMethod,
            $payload['amount_received'] ?? null,
            $total
        );

        return [Sale::PAYMENT_STATUS_PAID, $paymentMethod, $total, 0.0, $amountReceived, $changeAmount];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: string|null, 2: float, 3: float, 4: float|null, 5: float|null}
     */
    private function resolvePartialPaymentData(
        array $payload,
        float $total,
        ?string $paymentMethod,
        ?Customer $customer
    ): array {
        if ($customer === null) {
            throw ValidationException::withMessages([
                'customer_id' => 'Debes seleccionar un cliente para registrar un saldo pendiente.',
            ]);
        }

        if ($paymentMethod === null) {
            throw ValidationException::withMessages([
                'payment_method' => 'Debes seleccionar un medio de pago para el cobro inicial.',
            ]);
        }

        $paidAmount = round((float) ($payload['paid_amount'] ?? 0), 2);

        if ($paidAmount <= 0 || $paidAmount >= $total) {
            throw ValidationException::withMessages([
                'paid_amount' => 'El monto abonado debe ser mayor a 0 y menor al total de la venta.',
            ]);
        }

        [$amountReceived, $changeAmount] = $this->resolveCollectedAmounts(
            $paymentMethod,
            $payload['amount_received'] ?? null,
            $paidAmount
        );

        return [
            Sale::PAYMENT_STATUS_PARTIAL,
            $paymentMethod,
            $paidAmount,
            round($total - $paidAmount, 2),
            $amountReceived,
            $changeAmount,
        ];
    }

    /**
     * @return array{0: string, 1: string|null, 2: float, 3: float, 4: float|null, 5: float|null}
     */
    private function resolvePendingPaymentData(?Customer $customer, float $total): array
    {
        if ($customer === null) {
            throw ValidationException::withMessages([
                'customer_id' => 'Debes seleccionar un cliente para registrar una venta fiada.',
            ]);
        }

        return [Sale::PAYMENT_STATUS_PENDING, null, 0.0, $total, null, null];
    }

    /**
     * @return array{0: float|null, 1: float|null}
     */
    private function resolveCollectedAmounts(?string $paymentMethod, mixed $amountReceivedValue, float $expectedAmount): array
    {
        if ($paymentMethod !== 'cash') {
            return [null, null];
        }

        $amountReceived = $amountReceivedValue === null || $amountReceivedValue === ''
            ? $expectedAmount
            : round((float) $amountReceivedValue, 2);

        if ($amountReceived < $expectedAmount) {
            throw ValidationException::withMessages([
                'amount_received' => 'El monto recibido no puede ser menor al cobro informado.',
            ]);
        }

        return [$amountReceived, round($amountReceived - $expectedAmount, 2)];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: int|null, 1: int|null}
     */
    private function resolveAdvancedSaleContext(Business $business, array $payload, float $paidAmount): array
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

        if ($paidAmount <= 0) {
            return [$saleSectorId, null];
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

    private function resolveCustomer(Business $business, mixed $customerId): ?Customer
    {
        if ($customerId === null || $customerId === '') {
            return null;
        }

        $customer = Customer::query()
            ->forBusiness($business->id)
            ->whereKey((int) $customerId)
            ->first();

        if ($customer === null) {
            throw ValidationException::withMessages([
                'customer_id' => 'El cliente seleccionado no pertenece a este comercio.',
            ]);
        }

        return $customer;
    }
}
