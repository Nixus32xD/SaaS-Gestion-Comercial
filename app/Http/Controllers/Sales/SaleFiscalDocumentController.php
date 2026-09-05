<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleFiscalDocument;
use App\Services\Fiscal\FiscalApiException;
use App\Services\Fiscal\FiscalApiTimeoutException;
use App\Services\Fiscal\FiscalPdfService;
use App\Services\Fiscal\FiscalSaleDocumentService;
use App\Services\Fiscal\FiscalSalePayloadBuilder;
use App\Support\CurrentBranch;
use App\Support\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class SaleFiscalDocumentController extends Controller
{
    public function __construct(
        private readonly FiscalSaleDocumentService $fiscalSaleDocumentService,
        private readonly FiscalPdfService $fiscalPdfService,
        private readonly FiscalSalePayloadBuilder $fiscalPayloadBuilder,
    ) {}

    public function store(CurrentBusiness $currentBusiness, CurrentBranch $currentBranch, Sale $sale): RedirectResponse
    {
        $business = $currentBusiness->get();
        $branch = $currentBranch->get();
        abort_if($business === null || $branch === null, 404);
        abort_if($sale->business_id !== $business->id || $sale->branch_id !== $branch->id, 403);
        abort_unless((bool) config('fiscal.enabled') && $this->fiscalPayloadBuilder->isEnabledForSale($sale), 403);

        $document = $this->fiscalSaleDocumentService->issue($sale);

        return $this->redirectWithFiscalStatus($document);
    }

    public function reconcile(
        CurrentBusiness $currentBusiness,
        CurrentBranch $currentBranch,
        Sale $sale,
        SaleFiscalDocument $saleFiscalDocument
    ): RedirectResponse {
        $business = $currentBusiness->get();
        $branch = $currentBranch->get();
        abort_if($business === null || $branch === null, 404);
        abort_if($sale->business_id !== $business->id || $sale->branch_id !== $branch->id, 403);
        abort_if($saleFiscalDocument->business_id !== $business->id, 403);
        abort_if($saleFiscalDocument->sale_id !== $sale->id, 403);
        abort_unless((bool) config('fiscal.enabled') && $this->fiscalPayloadBuilder->isEnabledForSale($sale), 403);

        $document = $this->fiscalSaleDocumentService->reconcile($saleFiscalDocument);

        return $this->redirectWithFiscalStatus($document);
    }

    public function downloadPdf(
        CurrentBusiness $currentBusiness,
        CurrentBranch $currentBranch,
        Sale $sale,
        SaleFiscalDocument $saleFiscalDocument
    ): Response {
        $business = $currentBusiness->get();
        $branch = $currentBranch->get();
        abort_if($business === null || $branch === null, 404);
        abort_if($sale->business_id !== $business->id || $sale->branch_id !== $branch->id, 403);
        abort_if($saleFiscalDocument->business_id !== $business->id, 403);
        abort_if($saleFiscalDocument->sale_id !== $sale->id, 403);
        abort_unless((bool) config('fiscal.enabled') && $business->hasElectronicBilling(), 403);

        try {
            return $this->fiscalPdfService->download($saleFiscalDocument);
        } catch (FiscalApiTimeoutException) {
            abort(503, 'La API fiscal no respondió al recuperar el PDF autorizado. Intenta nuevamente.');
        } catch (FiscalApiException) {
            abort(502, 'La API fiscal no pudo entregar el PDF autorizado. Intenta nuevamente.');
        } catch (ValidationException $exception) {
            abort(422, collect($exception->errors())->flatten()->first() ?: 'No se pudo generar el PDF fiscal.');
        }
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
            return back()->with('error', $this->fiscalErrorMessage(
                $document,
                'El estado fiscal quedo incierto. Usar Conciliar antes de reintentar.'
            ));
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
