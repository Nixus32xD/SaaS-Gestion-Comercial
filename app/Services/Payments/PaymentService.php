<?php

namespace App\Services\Payments;

use App\Enums\Payments\PaymentMethod;
use App\Enums\Payments\PaymentProvider;
use App\Enums\Payments\PaymentStatus;
use App\Models\Business;
use App\Models\BusinessPaymentDestination;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function createManualPaymentForSale(
        Business $business,
        Sale $sale,
        User $user,
        string $method,
        float $amount,
        ?int $paymentDestinationId = null,
        ?string $idempotencyKey = null,
        array $metadata = [],
        mixed $paidAt = null
    ): Payment {
        return DB::transaction(function () use (
            $business,
            $sale,
            $user,
            $method,
            $amount,
            $paymentDestinationId,
            $idempotencyKey,
            $metadata,
            $paidAt
        ): Payment {
            $lockedSale = Sale::query()
                ->forBusiness($business->id)
                ->whereKey($sale->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedSale->business_id !== (int) $user->business_id) {
                throw ValidationException::withMessages([
                    'payment' => 'El usuario no pertenece al comercio de la venta.',
                ]);
            }

            $paymentMethod = PaymentMethod::tryFrom($method);

            if ($paymentMethod === null) {
                throw ValidationException::withMessages([
                    'payment_method' => 'El medio de pago seleccionado no es valido.',
                ]);
            }

            $normalizedAmount = round($amount, 2);

            if ($normalizedAmount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'El monto del pago debe ser mayor a cero.',
                ]);
            }

            if ($idempotencyKey !== null && trim($idempotencyKey) !== '') {
                $existing = Payment::query()
                    ->forBusiness($business->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing !== null) {
                    if ((int) $existing->sale_id !== (int) $lockedSale->id) {
                        throw ValidationException::withMessages([
                            'idempotency_key' => 'La clave de idempotencia ya fue usada en otra venta.',
                        ]);
                    }

                    $this->syncSalePaymentSummary($lockedSale);

                    return $existing;
                }
            }

            $this->assertPaymentDoesNotOverpaySale($lockedSale, $normalizedAmount);
            $this->assertPaymentDestination($business, $paymentDestinationId);

            $timestamp = $this->resolveDateTimeValue($paidAt);

            $payment = Payment::query()->create([
                'business_id' => $business->id,
                'sale_id' => $lockedSale->id,
                'created_by' => $user->id,
                'payment_destination_id' => $paymentDestinationId,
                'method' => $paymentMethod->value,
                'provider' => PaymentProvider::Manual->value,
                'status' => PaymentStatus::Approved->value,
                'amount' => $normalizedAmount,
                'currency' => 'ARS',
                'idempotency_key' => $idempotencyKey !== null && trim($idempotencyKey) !== ''
                    ? trim($idempotencyKey)
                    : null,
                'metadata' => [
                    ...$metadata,
                    'confirmed_manually' => true,
                ],
                'requested_at' => $timestamp,
                'approved_at' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $this->syncSalePaymentSummary($lockedSale);

            return $payment;
        });
    }

    public function syncSalePaymentSummary(Sale $sale): Sale
    {
        $lockedSale = Sale::query()
            ->whereKey($sale->id)
            ->lockForUpdate()
            ->firstOrFail();

        $approvedPayments = Payment::query()
            ->where('sale_id', $lockedSale->id)
            ->where('business_id', $lockedSale->business_id)
            ->where('status', PaymentStatus::Approved->value)
            ->orderBy('approved_at')
            ->orderBy('id')
            ->get();

        $approvedTotal = round((float) $approvedPayments->sum('amount'), 2);
        $saleTotal = round((float) $lockedSale->total, 2);
        $paidAmount = min($approvedTotal, $saleTotal);
        $pendingAmount = round(max($saleTotal - $paidAmount, 0), 2);
        $firstPayment = $approvedPayments->first();

        $lockedSale->forceFill([
            'payment_method' => $firstPayment?->method,
            'payment_status' => $this->saleStatusForAmounts($paidAmount, $pendingAmount),
            'payment_destination_id' => $firstPayment?->payment_destination_id,
            'paid_amount' => $paidAmount,
            'pending_amount' => $pendingAmount,
        ])->save();

        return $lockedSale->refresh();
    }

    private function assertPaymentDoesNotOverpaySale(Sale $sale, float $amount): void
    {
        $approvedTotal = round((float) Payment::query()
            ->where('sale_id', $sale->id)
            ->where('business_id', $sale->business_id)
            ->where('status', PaymentStatus::Approved->value)
            ->sum('amount'), 2);

        if (round($approvedTotal + $amount, 2) > round((float) $sale->total, 2)) {
            throw ValidationException::withMessages([
                'amount' => 'El pago no puede superar el saldo pendiente de la venta.',
            ]);
        }
    }

    private function assertPaymentDestination(Business $business, ?int $paymentDestinationId): void
    {
        if ($paymentDestinationId === null) {
            return;
        }

        $exists = BusinessPaymentDestination::query()
            ->forBusiness($business->id)
            ->whereKey($paymentDestinationId)
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'payment_destination_id' => 'El destino de cobro seleccionado no esta disponible para este comercio.',
            ]);
        }
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

    private function saleStatusForAmounts(float $paidAmount, float $pendingAmount): string
    {
        if ($pendingAmount <= 0) {
            return Sale::PAYMENT_STATUS_PAID;
        }

        return $paidAmount > 0
            ? Sale::PAYMENT_STATUS_PARTIAL
            : Sale::PAYMENT_STATUS_PENDING;
    }
}
