<?php

namespace App\Services\Payments\MercadoPago;

use App\Enums\Payments\PaymentStatus;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleFiscalDocument;
use App\Services\Fiscal\FiscalSaleDocumentService;
use App\Services\Fiscal\FiscalSalePayloadBuilder;
use App\Services\Payments\PaymentService;
use App\Services\SaleStockReservationService;
use Illuminate\Support\Facades\DB;
use Throwable;

class MercadoPagoPaymentCompletionService
{
    public function __construct(
        private readonly FiscalSaleDocumentService $fiscalSaleDocumentService,
        private readonly FiscalSalePayloadBuilder $fiscalPayloadBuilder,
        private readonly SaleStockReservationService $stockReservationService,
        private readonly PaymentService $paymentService,
    ) {}

    public function complete(Payment $payment): void
    {
        $payment->loadMissing(['sale.business', 'sale.latestFiscalDocument']);
        $sale = $payment->sale;
        $business = $sale?->business;

        if ($sale === null || $business === null) {
            return;
        }

        $sale->refresh()->loadMissing(['business', 'latestFiscalDocument']);

        $isPointSale = $sale->point_status !== null;

        if (in_array($payment->status, [
            PaymentStatus::Rejected->value,
            PaymentStatus::Cancelled->value,
        ], true)) {
            if ($isPointSale) {
                $this->stockReservationService->release($sale);
                $this->setPointStatus($sale, $this->terminalPointStatus($payment), $this->terminalReason($payment));
            }

            return;
        }

        if ($payment->status !== PaymentStatus::Approved->value) {
            return;
        }

        if ($isPointSale) {
            if ($sale->point_status !== Sale::POINT_STATUS_PENDING) {
                return;
            }

            $this->stockReservationService->consume($sale);
            $this->setPointStatus($sale, Sale::POINT_STATUS_APPROVED, 'point_approved');
        }

        $sale->refresh()->loadMissing(['business', 'latestFiscalDocument']);

        if (round((float) $sale->pending_amount, 2) > 0) {
            return;
        }

        if (! (bool) config('fiscal.enabled') || ! $this->fiscalPayloadBuilder->isEnabledForSale($sale)) {
            return;
        }

        $latestDocument = $sale->latestFiscalDocument;

        if ($latestDocument !== null && $latestDocument->fiscal_status !== SaleFiscalDocument::STATUS_ERROR) {
            return;
        }

        try {
            $this->fiscalSaleDocumentService->issue($sale);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function cancel(Payment $payment, string $reason, string $pointStatus = Sale::POINT_STATUS_CANCELLED): Payment
    {
        return DB::transaction(function () use ($payment, $reason, $pointStatus): Payment {
            $payment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();
            $sale = Sale::query()
                ->whereKey($payment->sale_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status !== PaymentStatus::Pending->value
                || $sale->point_status !== Sale::POINT_STATUS_PENDING) {
                return $payment;
            }

            $payment->forceFill([
                'status' => PaymentStatus::Cancelled->value,
                'provider_status' => $payment->provider_status ?: 'cancelled_locally',
                'cancelled_at' => now(),
                'metadata' => [
                    ...((array) $payment->metadata),
                    'cancellation_reason' => $reason,
                ],
            ])->save();

            $this->stockReservationService->release($sale);
            $this->paymentService->syncSalePaymentSummary($sale);
            $this->setPointStatus($sale, $pointStatus, $reason);

            return $payment->refresh();
        });
    }

    private function setPointStatus(Sale $sale, string $status, string $reason): void
    {
        DB::transaction(function () use ($sale, $status, $reason): void {
            $sale = Sale::query()
                ->whereKey($sale->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($sale->point_status !== Sale::POINT_STATUS_PENDING) {
                return;
            }

            $sale->forceFill([
                'point_status' => $status,
                'point_status_reason' => $reason,
                'point_status_changed_at' => now(),
            ])->save();
        });
    }

    private function terminalPointStatus(Payment $payment): string
    {
        if ($payment->provider_status === 'expired') {
            return Sale::POINT_STATUS_EXPIRED;
        }

        return $payment->status === PaymentStatus::Rejected->value
            ? Sale::POINT_STATUS_REJECTED
            : Sale::POINT_STATUS_CANCELLED;
    }

    private function terminalReason(Payment $payment): string
    {
        return match ($payment->provider_status) {
            'expired' => 'point_expired',
            'failed' => 'point_rejected',
            default => 'point_cancelled',
        };
    }
}
