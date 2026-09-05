<?php

namespace App\Services;

use App\Enums\Payments\PaymentProvider;
use App\Enums\Payments\PaymentStatus;
use App\Models\Business;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Sale;
use App\Models\SaleFiscalDocument;

class OperationalMonitoringService
{
    /**
     * @return list<array{key: string, label: string, count: int, tone: string, description: string}>
     */
    public function forBusiness(Business $business, ?int $branchId = null): array
    {
        $saleScope = fn ($query) => $query
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId));

        $pendingPointPayments = Payment::query()
            ->forBusiness($business->id)
            ->where('provider', PaymentProvider::MercadoPago->value)
            ->where('status', PaymentStatus::Pending->value)
            ->where('requested_at', '<=', now()->subMinutes(10))
            ->whereHas('sale', fn ($query) => $saleScope($query)->where('point_status', Sale::POINT_STATUS_PENDING))
            ->count();

        $reconciliationRequired = Payment::query()
            ->forBusiness($business->id)
            ->where('provider', PaymentProvider::MercadoPago->value)
            ->whereHas('sale', fn ($query) => $saleScope($query)->where('point_status', Sale::POINT_STATUS_RECONCILIATION_REQUIRED))
            ->count();

        $pendingWebhooks = PaymentEvent::query()
            ->forBusiness($business->id)
            ->where('provider', PaymentProvider::MercadoPago->value)
            ->whereNull('processed_at')
            ->whereHas('payment.sale', $saleScope)
            ->count();

        $fiscalAttentionRequired = SaleFiscalDocument::query()
            ->forBusiness($business->id)
            ->whereIn('fiscal_status', [
                SaleFiscalDocument::STATUS_UNCERTAIN,
                SaleFiscalDocument::STATUS_ERROR,
            ])
            ->whereHas('sale', $saleScope)
            ->count();

        return [
            [
                'key' => 'point_pending',
                'label' => 'Point pendientes',
                'count' => $pendingPointPayments,
                'tone' => $pendingPointPayments > 0 ? 'warning' : 'success',
                'description' => 'Ordenes Point pendientes hace mas de 10 minutos.',
            ],
            [
                'key' => 'point_reconciliation',
                'label' => 'Conciliacion Point',
                'count' => $reconciliationRequired,
                'tone' => $reconciliationRequired > 0 ? 'danger' : 'success',
                'description' => 'Pagos aprobados fuera de la ventana local que requieren revision.',
            ],
            [
                'key' => 'webhooks_pending',
                'label' => 'Webhooks pendientes',
                'count' => $pendingWebhooks,
                'tone' => $pendingWebhooks > 0 ? 'warning' : 'success',
                'description' => 'Eventos validos aun no marcados como procesados.',
            ],
            [
                'key' => 'fiscal_attention',
                'label' => 'Fiscal a revisar',
                'count' => $fiscalAttentionRequired,
                'tone' => $fiscalAttentionRequired > 0 ? 'danger' : 'success',
                'description' => 'Comprobantes fiscales inciertos o con error.',
            ],
        ];
    }
}
