<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleFiscalDocument;
use App\Services\Fiscal\FiscalSaleDocumentService;
use App\Support\CurrentBusiness;
use Illuminate\Http\RedirectResponse;

class SaleFiscalDocumentController extends Controller
{
    public function __construct(private readonly FiscalSaleDocumentService $fiscalSaleDocumentService) {}

    public function store(CurrentBusiness $currentBusiness, Sale $sale): RedirectResponse
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);
        abort_if($sale->business_id !== $business->id, 403);
        abort_unless((bool) config('fiscal.enabled') && $business->hasElectronicBilling(), 403);

        $document = $this->fiscalSaleDocumentService->issue($sale);

        return $this->redirectWithFiscalStatus($document);
    }

    public function reconcile(
        CurrentBusiness $currentBusiness,
        Sale $sale,
        SaleFiscalDocument $saleFiscalDocument
    ): RedirectResponse {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);
        abort_if($sale->business_id !== $business->id, 403);
        abort_if($saleFiscalDocument->business_id !== $business->id, 403);
        abort_if($saleFiscalDocument->sale_id !== $sale->id, 403);
        abort_unless((bool) config('fiscal.enabled') && $business->hasElectronicBilling(), 403);

        $document = $this->fiscalSaleDocumentService->reconcile($saleFiscalDocument);

        return $this->redirectWithFiscalStatus($document);
    }

    private function redirectWithFiscalStatus(SaleFiscalDocument $document): RedirectResponse
    {
        if ($document->fiscal_status === SaleFiscalDocument::STATUS_AUTHORIZED) {
            return back()->with('success', 'Comprobante fiscal autorizado correctamente.');
        }

        if ($document->fiscal_status === SaleFiscalDocument::STATUS_REJECTED) {
            return back()->with('error', $this->fiscalErrorMessage(
                $document,
                'La API fiscal rechazo el comprobante. Revisar el detalle fiscal de la venta.'
            ));
        }

        if ($document->fiscal_status === SaleFiscalDocument::STATUS_UNCERTAIN) {
            return back()->with('error', 'El estado fiscal quedo incierto. Usar Conciliar antes de reintentar.');
        }

        return back()->with('error', $this->fiscalErrorMessage(
            $document,
            'No se pudo autorizar el comprobante fiscal.'
        ));
    }

    private function fiscalErrorMessage(SaleFiscalDocument $document, string $fallback): string
    {
        return $document->fiscal_error_message !== null && $document->fiscal_error_message !== ''
            ? $document->fiscal_error_message
            : $fallback;
    }
}
