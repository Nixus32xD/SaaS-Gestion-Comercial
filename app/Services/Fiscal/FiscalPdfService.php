<?php

namespace App\Services\Fiscal;

use App\Models\SaleFiscalDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class FiscalPdfService
{
    public function __construct(
        private readonly FiscalApiClient $client,
        private readonly FiscalQrService $qrService,
    ) {}

    public function download(SaleFiscalDocument $document): Response
    {
        if ($document->fiscal_document_id !== null && $document->fiscal_document_id !== '') {
            return $this->downloadFromFiscalApi($document);
        }

        return $this->downloadLegacyDocument($document);
    }

    private function downloadFromFiscalApi(SaleFiscalDocument $document): Response
    {
        $response = $this->client->documentPdf($document->fiscal_document_id);
        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $response->header(
                'Content-Disposition',
                'inline; filename="'.$this->filename($document).'"'
            ),
        ];
        $hash = $response->header('X-Fiscal-PDF-SHA256');

        if ($hash !== null && $hash !== '') {
            $headers['X-Fiscal-PDF-SHA256'] = $hash;
        }

        return response($response->body(), 200, $headers);
    }

    /**
     * Documents issued before apiArca stored a remote id have no canonical
     * remote PDF to retrieve. Keep this renderer only for that historical data.
     */
    private function downloadLegacyDocument(SaleFiscalDocument $document): Response
    {
        $document->loadMissing(['sale.business', 'sale.customer', 'sale.items.product']);

        $pdf = Pdf::loadView('pdf.fiscal-document', [
            'document' => $document,
            'sale' => $document->sale,
            'business' => $document->sale->business,
            'issuerCuit' => $this->qrService->issuerCuit($document),
            'issuerLegalName' => $document->issuer_legal_name
                ?? data_get($document->fiscal_response ?? [], 'data.company.legal_name')
                ?? $document->sale->business->name,
            'issuerFiscalCondition' => $document->issuer_fiscal_condition
                ?? data_get($document->fiscal_response ?? [], 'data.company.fiscal_condition'),
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
