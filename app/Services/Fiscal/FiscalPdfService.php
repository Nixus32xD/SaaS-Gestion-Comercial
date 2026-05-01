<?php

namespace App\Services\Fiscal;

use App\Models\SaleFiscalDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class FiscalPdfService
{
    public function __construct(private readonly FiscalQrService $qrService) {}

    public function download(SaleFiscalDocument $document): Response
    {
        $document->loadMissing(['sale.business', 'sale.customer', 'sale.items.product']);

        $pdf = Pdf::loadView('pdf.fiscal-document', [
            'document' => $document,
            'sale' => $document->sale,
            'business' => $document->sale->business,
            'qrUrl' => $this->qrService->url($document),
            'qrImage' => $this->qrService->imageDataUri($document),
            'voucherLabel' => $this->voucherLabel($document),
            'authorizationLabel' => $document->authorization_type ?? SaleFiscalDocument::AUTHORIZATION_CAE,
        ])->setPaper('a4');

        return $pdf->download($this->filename($document));
    }

    public function filename(SaleFiscalDocument $document): string
    {
        $pointOfSale = str_pad((string) $document->fiscal_point_of_sale, 5, '0', STR_PAD_LEFT);
        $number = str_pad((string) $document->fiscal_number, 8, '0', STR_PAD_LEFT);

        return "comprobante-fiscal-{$pointOfSale}-{$number}.pdf";
    }

    private function voucherLabel(SaleFiscalDocument $document): string
    {
        $type = collect(config('fiscal.voucher_types', []))
            ->firstWhere('value', (int) $document->fiscal_cbte_type);

        return (string) ($type['label'] ?? "Comprobante {$document->fiscal_cbte_type}");
    }
}
