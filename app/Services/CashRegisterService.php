<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Business;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashRegisterService
{
    /** @var list<string> */
    private const MANUAL_MOVEMENT_TYPES = [
        CashMovement::TYPE_MANUAL_INCOME,
        CashMovement::TYPE_MANUAL_EXPENSE,
        CashMovement::TYPE_ADJUSTMENT_IN,
        CashMovement::TYPE_ADJUSTMENT_OUT,
    ];

    public function open(Business $business, Branch $branch, User $user, float $openingAmount, ?string $notes = null): CashSession
    {
        $this->assertContext($business, $branch, $user);
        $openingAmount = round($openingAmount, 2);

        if ($openingAmount < 0) {
            throw ValidationException::withMessages(['opening_amount' => 'El monto inicial no puede ser negativo.']);
        }

        try {
            return DB::transaction(function () use ($business, $branch, $user, $openingAmount, $notes): CashSession {
                $openSession = $this->lockedOpenSession($business, $branch);
                if ($openSession !== null) {
                    throw ValidationException::withMessages(['cash_session' => 'Ya hay una caja abierta para esta sucursal.']);
                }

                return CashSession::query()->create([
                    'business_id' => $business->id,
                    'branch_id' => $branch->id,
                    'opened_by' => $user->id,
                    'opened_at' => now(),
                    'opening_amount' => $openingAmount,
                    'opening_notes' => $this->normalizedText($notes),
                    'status' => CashSession::STATUS_OPEN,
                    'open_marker' => 1,
                ]);
            }, attempts: 3);
        } catch (QueryException $exception) {
            if (CashSession::query()->forBusiness($business->id)->where('branch_id', $branch->id)->where('status', CashSession::STATUS_OPEN)->exists()) {
                throw ValidationException::withMessages(['cash_session' => 'Ya hay una caja abierta para esta sucursal.']);
            }

            throw $exception;
        }
    }

    public function recordManualMovement(
        Business $business,
        Branch $branch,
        User $user,
        string $type,
        float $amount,
        ?string $description = null,
    ): CashMovement {
        $this->assertContext($business, $branch, $user);
        $amount = round($amount, 2);

        if (! in_array($type, self::MANUAL_MOVEMENT_TYPES, true)) {
            throw ValidationException::withMessages(['type' => 'El tipo de movimiento de caja no es válido.']);
        }
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'El importe debe ser mayor a cero.']);
        }
        if (in_array($type, [CashMovement::TYPE_ADJUSTMENT_IN, CashMovement::TYPE_ADJUSTMENT_OUT], true) && ! $user->isBusinessAdmin()) {
            throw ValidationException::withMessages(['type' => 'Solo un administrador puede registrar ajustes de caja.']);
        }

        return DB::transaction(function () use ($business, $branch, $user, $type, $amount, $description): CashMovement {
            $session = $this->requireLockedOpenSession($business, $branch);

            return CashMovement::query()->create([
                'business_id' => $business->id,
                'branch_id' => $branch->id,
                'cash_session_id' => $session->id,
                'created_by' => $user->id,
                'type' => $type,
                'amount' => $this->signedAmount($type, $amount),
                'description' => $this->normalizedText($description),
                'occurred_at' => now(),
            ]);
        }, attempts: 3);
    }

    /**
     * Records a physical cash collection exactly once when there is an open drawer
     * in the branch of the sale. Payments made while no drawer is open are left
     * untouched to preserve the existing sales workflow.
     */
    public function recordCashPayment(Payment $payment): ?CashMovement
    {
        if (! $payment->isApproved() || $payment->method !== Payment::METHOD_CASH) {
            return null;
        }

        return DB::transaction(function () use ($payment): ?CashMovement {
            $payment = Payment::query()
                ->with('sale:id,business_id,branch_id,sale_number')
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $payment->isApproved() || $payment->method !== Payment::METHOD_CASH || $payment->sale === null) {
                return null;
            }

            $session = CashSession::query()
                ->forBusiness($payment->business_id)
                ->where('branch_id', $payment->sale->branch_id)
                ->where('status', CashSession::STATUS_OPEN)
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                return null;
            }

            $existing = CashMovement::query()
                ->forBusiness($payment->business_id)
                ->where('reference_type', Payment::class)
                ->where('reference_id', $payment->id)
                ->first();
            if ($existing !== null) {
                return $existing;
            }

            return CashMovement::query()->create([
                'business_id' => $payment->business_id,
                'branch_id' => $payment->sale->branch_id,
                'cash_session_id' => $session->id,
                'created_by' => $payment->created_by,
                'type' => CashMovement::TYPE_CASH_SALE,
                'amount' => $payment->amount,
                'reference_type' => Payment::class,
                'reference_id' => $payment->id,
                'description' => "Cobro en efectivo de venta {$payment->sale->sale_number}",
                'occurred_at' => $payment->approved_at ?? $payment->created_at ?? now(),
            ]);
        }, attempts: 3);
    }

    public function close(Business $business, Branch $branch, User $user, float $countedAmount, ?string $notes = null): CashSession
    {
        $this->assertContext($business, $branch, $user);
        $countedAmount = round($countedAmount, 2);
        if ($countedAmount < 0) {
            throw ValidationException::withMessages(['counted_amount' => 'El dinero contado no puede ser negativo.']);
        }

        return DB::transaction(function () use ($business, $branch, $user, $countedAmount, $notes): CashSession {
            $session = $this->requireLockedOpenSession($business, $branch);
            $movements = CashMovement::query()
                ->where('cash_session_id', $session->id)
                ->lockForUpdate()
                ->get(['id', 'amount']);
            $expectedAmount = round((float) $session->opening_amount + (float) $movements->sum('amount'), 2);

            $session->forceFill([
                'status' => CashSession::STATUS_CLOSED,
                'open_marker' => null,
                'closed_by' => $user->id,
                'closed_at' => now(),
                'expected_amount_at_close' => $expectedAmount,
                'counted_amount' => $countedAmount,
                'difference_amount' => round($countedAmount - $expectedAmount, 2),
                'closing_notes' => $this->normalizedText($notes),
            ])->save();

            return $session->refresh();
        }, attempts: 3);
    }

    /** @return array{session: CashSession|null, expected_amount: float, totals: array<string, float>} */
    public function currentSummary(Business $business, Branch $branch): array
    {
        $session = CashSession::query()
            ->forBusiness($business->id)
            ->where('branch_id', $branch->id)
            ->where('status', CashSession::STATUS_OPEN)
            ->with(['movements' => fn ($query) => $query->orderBy('occurred_at')->orderBy('id')])
            ->first();

        if ($session === null) {
            return ['session' => null, 'expected_amount' => 0.0, 'totals' => $this->emptyTotals()];
        }

        return $this->summaryForSession($session);
    }

    /** @return array{session: CashSession, expected_amount: float, totals: array<string, float>} */
    public function summaryForSession(CashSession $session): array
    {
        $session->loadMissing(['movements' => fn ($query) => $query->orderBy('occurred_at')->orderBy('id')]);
        $totals = $this->emptyTotals();
        foreach ($session->movements as $movement) {
            $totals[$movement->type] = round(($totals[$movement->type] ?? 0) + (float) $movement->amount, 2);
        }

        return [
            'session' => $session,
            'expected_amount' => round((float) $session->opening_amount + array_sum($totals), 2),
            'totals' => $totals,
        ];
    }

    private function requireLockedOpenSession(Business $business, Branch $branch): CashSession
    {
        $session = $this->lockedOpenSession($business, $branch);
        if ($session === null) {
            throw ValidationException::withMessages(['cash_session' => 'No hay una caja abierta para esta sucursal.']);
        }

        return $session;
    }

    private function lockedOpenSession(Business $business, Branch $branch): ?CashSession
    {
        return CashSession::query()
            ->forBusiness($business->id)
            ->where('branch_id', $branch->id)
            ->where('status', CashSession::STATUS_OPEN)
            ->lockForUpdate()
            ->first();
    }

    private function assertContext(Business $business, Branch $branch, User $user): void
    {
        if ((int) $branch->business_id !== (int) $business->id
            || (int) $user->business_id !== (int) $business->id
            || ! $branch->is_active
            || ! $user->isBusinessUser()) {
            throw ValidationException::withMessages(['branch_id' => 'La sucursal o el usuario no pertenecen al comercio actual.']);
        }
    }

    private function signedAmount(string $type, float $amount): float
    {
        return in_array($type, [CashMovement::TYPE_MANUAL_EXPENSE, CashMovement::TYPE_ADJUSTMENT_OUT, CashMovement::TYPE_REFUND], true)
            ? -1 * $amount
            : $amount;
    }

    /** @return array<string, float> */
    private function emptyTotals(): array
    {
        return array_fill_keys([
            CashMovement::TYPE_MANUAL_INCOME,
            CashMovement::TYPE_MANUAL_EXPENSE,
            CashMovement::TYPE_CASH_SALE,
            CashMovement::TYPE_REFUND,
            CashMovement::TYPE_ADJUSTMENT_IN,
            CashMovement::TYPE_ADJUSTMENT_OUT,
        ], 0.0);
    }

    private function normalizedText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
