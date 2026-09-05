<?php

namespace App\Services\Fiscal;

use App\Models\Business;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleFiscalDocument;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class FiscalMonthlyReportService
{
    /**
     * Uses the already persisted fiscal sale breakdown (and authorized documents)
     * instead of recalculating IVA Ventas from sale items.
     *
     * @return array<string, mixed>
     */
    public function build(Business $business, CarbonImmutable $from, CarbonImmutable $to, ?int $branchId): array
    {
        $sales = Sale::query()
            ->forBusiness($business->id)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->whereBetween('sold_at', [$from->startOfDay(), $to->endOfDay()])
            ->whereHas('fiscalDocuments', fn ($query) => $query->where('fiscal_status', SaleFiscalDocument::STATUS_AUTHORIZED))
            ->selectRaw('COALESCE(SUM(fiscal_net_amount), 0) as net_amount')
            ->selectRaw('COALESCE(SUM(fiscal_vat_amount), 0) as vat_amount')
            ->selectRaw('COALESCE(SUM(fiscal_exempt_amount), 0) as exempt_amount')
            ->selectRaw('COALESCE(SUM(fiscal_non_taxed_amount), 0) as non_taxed_amount')
            ->first();

        /** @var Collection<int, Purchase> $purchases */
        $purchases = Purchase::query()
            ->forBusiness($business->id)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->whereNotNull('fiscal_document_type')
            ->whereBetween('fiscal_voucher_date', [$from->toDateString(), $to->toDateString()])
            ->with(['supplier:id,name', 'branch:id,name', 'fiscalItems'])
            ->orderBy('fiscal_voucher_date')
            ->orderBy('id')
            ->get();

        $purchaseTotals = [
            'net_amount' => round((float) $purchases->sum('fiscal_net_amount'), 2),
            'vat_amount' => round((float) $purchases->sum('fiscal_vat_amount'), 2),
            'exempt_amount' => round((float) $purchases->sum('fiscal_exempt_amount'), 2),
            'non_taxed_amount' => round((float) $purchases->sum('fiscal_non_taxed_amount'), 2),
            'other_taxes_amount' => round((float) $purchases->sum('fiscal_other_taxes_amount'), 2),
            'total_amount' => round((float) $purchases->sum('fiscal_total_amount'), 2),
        ];
        $salesTotals = [
            'net_amount' => round((float) ($sales?->net_amount ?? 0), 2),
            'vat_amount' => round((float) ($sales?->vat_amount ?? 0), 2),
            'exempt_amount' => round((float) ($sales?->exempt_amount ?? 0), 2),
            'non_taxed_amount' => round((float) ($sales?->non_taxed_amount ?? 0), 2),
        ];

        return [
            'sales' => $salesTotals,
            'purchases' => $purchaseTotals,
            'estimated_difference' => round($salesTotals['vat_amount'] - $purchaseTotals['vat_amount'], 2),
            'purchase_records' => $purchases->map(fn (Purchase $purchase): array => [
                'id' => $purchase->id,
                'branch' => $purchase->branch?->name,
                'supplier' => $purchase->supplier?->name,
                'supplier_cuit' => $purchase->supplier_cuit,
                'document_type' => $purchase->fiscal_document_type,
                'point_of_sale' => $purchase->fiscal_point_of_sale,
                'number' => $purchase->fiscal_number,
                'voucher_date' => $purchase->fiscal_voucher_date?->toDateString(),
                'net_amount' => (float) $purchase->fiscal_net_amount,
                'vat_amount' => (float) $purchase->fiscal_vat_amount,
                'exempt_amount' => (float) $purchase->fiscal_exempt_amount,
                'non_taxed_amount' => (float) $purchase->fiscal_non_taxed_amount,
                'other_taxes_amount' => (float) $purchase->fiscal_other_taxes_amount,
                'total_amount' => (float) $purchase->fiscal_total_amount,
                'items' => $purchase->fiscalItems->map(fn ($item) => [
                    'vat_treatment' => $item->vat_treatment,
                    'vat_rate' => (float) $item->vat_rate,
                    'net_amount' => (float) $item->net_amount,
                    'vat_amount' => (float) $item->vat_amount,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
