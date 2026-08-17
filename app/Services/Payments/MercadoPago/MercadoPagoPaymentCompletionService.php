<?php

namespace App\Services\Payments\MercadoPago;

use App\Enums\Payments\PaymentStatus;
use App\Models\Payment;
use App\Models\SaleFiscalDocument;
use App\Services\Fiscal\FiscalSaleDocumentService;
use Throwable;

class MercadoPagoPaymentCompletionService
{
    public function __construct(
        private readonly FiscalSaleDocumentService $fiscalSaleDocumentService,
    ) {}

    public function complete(Payment $payment): void
    {
        if ($payment->status !== PaymentStatus::Approved->value) {
            return;
        }

        $payment->loadMissing(['sale.business', 'sale.latestFiscalDocument']);
        $sale = $payment->sale;
        $business = $sale?->business;

        if ($sale === null || $business === null) {
            return;
        }

        $sale->refresh()->loadMissing(['business', 'latestFiscalDocument']);

        if (round((float) $sale->pending_amount, 2) > 0) {
            return;
        }

        if (! (bool) config('fiscal.enabled') || ! $business->hasElectronicBilling()) {
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
}
