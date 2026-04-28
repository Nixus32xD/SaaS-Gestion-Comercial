<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerAccountService
{
    public function recordDebtForSale(
        Business $business,
        Sale $sale,
        Customer $customer,
        User $user,
        float $amount
    ): CustomerAccountMovement {
        $normalizedAmount = round($amount, 2);

        if ($normalizedAmount <= 0) {
            throw ValidationException::withMessages([
                'paid_amount' => 'El saldo pendiente debe ser mayor a cero para generar deuda.',
            ]);
        }

        $lockedCustomer = $this->lockCustomer($business, $customer->id);
        $currentBalance = $this->currentBalance($lockedCustomer);
        $nextBalance = round($currentBalance + $normalizedAmount, 2);

        return CustomerAccountMovement::query()->create([
            'business_id' => $business->id,
            'customer_id' => $lockedCustomer->id,
            'sale_id' => $sale->id,
            'type' => CustomerAccountMovement::TYPE_DEBT,
            'amount' => $normalizedAmount,
            'balance_after' => $nextBalance,
            'description' => "Saldo pendiente generado por la venta {$sale->sale_number}.",
            'meta' => [
                'sale_number' => $sale->sale_number,
                'payment_status' => $sale->payment_status,
            ],
            'created_by' => $user->id,
            'created_at' => $sale->sold_at ?? now(),
            'updated_at' => $sale->sold_at ?? now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function registerPayment(
        Business $business,
        Customer $customer,
        User $user,
        array $payload
    ): CustomerAccountMovement {
        return DB::transaction(function () use ($business, $customer, $user, $payload): CustomerAccountMovement {
            $lockedCustomer = $this->lockCustomer($business, $customer->id);
            $amount = round((float) ($payload['amount'] ?? 0), 2);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'El monto del pago debe ser mayor a cero.',
                ]);
            }

            $currentBalance = $this->currentBalance($lockedCustomer);

            if ($currentBalance <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'El cliente no tiene saldo pendiente para cancelar.',
                ]);
            }

            if ($amount > $currentBalance) {
                throw ValidationException::withMessages([
                    'amount' => 'El pago no puede superar el saldo pendiente actual del cliente.',
                ]);
            }

            $remaining = $amount;
            $allocations = [];

            $openSales = Sale::query()
                ->forBusiness($business->id)
                ->where('customer_id', $lockedCustomer->id)
                ->where('pending_amount', '>', 0)
                ->orderBy('sold_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($openSales as $sale) {
                if ($remaining <= 0) {
                    break;
                }

                $pendingAmount = round((float) $sale->pending_amount, 2);

                if ($pendingAmount <= 0) {
                    continue;
                }

                $appliedAmount = round(min($pendingAmount, $remaining), 2);
                $nextPending = round($pendingAmount - $appliedAmount, 2);
                $nextPaid = round((float) $sale->paid_amount + $appliedAmount, 2);

                $sale->update([
                    'paid_amount' => $nextPaid,
                    'pending_amount' => $nextPending,
                    'payment_status' => $nextPending > 0 ? Sale::PAYMENT_STATUS_PARTIAL : Sale::PAYMENT_STATUS_PAID,
                ]);

                $allocations[] = [
                    'sale_id' => $sale->id,
                    'sale_number' => $sale->sale_number,
                    'applied_amount' => $appliedAmount,
                    'sold_at' => $sale->sold_at?->format('Y-m-d H:i'),
                ];

                $remaining = round($remaining - $appliedAmount, 2);
            }

            if ($remaining > 0) {
                throw ValidationException::withMessages([
                    'amount' => 'No se pudo aplicar el pago sobre las ventas pendientes del cliente.',
                ]);
            }

            $nextBalance = round($currentBalance - $amount, 2);
            $paidAt = $this->resolveDateTimeValue($payload['paid_at'] ?? null);
            $description = trim((string) ($payload['description'] ?? ''));

            return CustomerAccountMovement::query()->create([
                'business_id' => $business->id,
                'customer_id' => $lockedCustomer->id,
                'sale_id' => count($allocations) === 1 ? $allocations[0]['sale_id'] : null,
                'type' => CustomerAccountMovement::TYPE_PAYMENT,
                'amount' => $amount,
                'balance_after' => $nextBalance,
                'description' => $description !== '' ? $description : 'Pago registrado en cuenta corriente.',
                'meta' => [
                    'payment_method' => $payload['payment_method'] ?? null,
                    'allocations' => $allocations,
                ],
                'created_by' => $user->id,
                'created_at' => $paidAt,
                'updated_at' => $paidAt,
            ]);
        });
    }

    public function currentBalance(Customer $customer): float
    {
        $balance = CustomerAccountMovement::query()
            ->forBusiness($customer->business_id)
            ->where('customer_id', $customer->id)
            ->selectRaw(
                "COALESCE(SUM(CASE
                    WHEN type = ? THEN amount
                    WHEN type = ? THEN -amount
                    WHEN type = ? THEN amount
                    ELSE 0
                END), 0) as balance",
                [
                    CustomerAccountMovement::TYPE_DEBT,
                    CustomerAccountMovement::TYPE_PAYMENT,
                    CustomerAccountMovement::TYPE_ADJUSTMENT,
                ]
            )
            ->value('balance');

        return round((float) $balance, 2);
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

    private function lockCustomer(Business $business, int $customerId): Customer
    {
        return Customer::query()
            ->forBusiness($business->id)
            ->whereKey($customerId)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
