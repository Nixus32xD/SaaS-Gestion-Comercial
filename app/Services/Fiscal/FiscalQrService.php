<?php

namespace App\Services\Fiscal;

use App\Models\SaleFiscalDocument;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class FiscalQrService
{
    private const BASE_URL = 'https://www.arca.gob.ar/fe/qr/?p=';

    /**
     * @return array<string, int|float|string>
     */
    public function payload(SaleFiscalDocument $document): array
    {
        $document->loadMissing(['sale.business']);

        if (! $document->isAuthorized()) {
            throw ValidationException::withMessages([
                'fiscal_document' => 'Solo se puede generar QR para comprobantes fiscales autorizados.',
            ]);
        }

        $sale = $document->sale;
        $business = $sale?->business;
        $fiscalPayload = $document->fiscal_payload ?? [];
        $customer = data_get($fiscalPayload, 'customer', []);

        $authorizationType = strtoupper((string) ($document->authorization_type
            ?? ($document->fiscal_cae !== null ? SaleFiscalDocument::AUTHORIZATION_CAE : '')));
        $authorizationCode = $document->authorization_code ?? $document->fiscal_cae;

        $payload = [
            'ver' => 1,
            'fecha' => $this->issueDate($document),
            'cuit' => $this->digitsAsInt($business?->fiscal_cuit, 'CUIT del emisor'),
            'ptoVta' => $this->positiveInt($document->fiscal_point_of_sale, 'punto de venta'),
            'tipoCmp' => $this->positiveInt($document->fiscal_cbte_type, 'tipo de comprobante'),
            'nroCmp' => $this->positiveInt($document->fiscal_number, 'numero de comprobante'),
            'importe' => round((float) ($sale?->total ?? data_get($fiscalPayload, 'amounts.imp_total')), 2),
            'moneda' => (string) data_get($fiscalPayload, 'currency', config('fiscal.defaults.currency', 'PES')),
            'ctz' => (float) data_get($fiscalPayload, 'currency_rate', config('fiscal.defaults.currency_rate', 1)),
            'tipoDocRec' => $this->receiverDocumentType($customer),
            'nroDocRec' => $this->receiverDocumentNumber($customer),
            'tipoCodAut' => $this->authorizationQrType($authorizationType),
            'codAut' => $this->digitsAsInt($authorizationCode, 'codigo de autorizacion'),
        ];

        if ($payload['importe'] <= 0) {
            throw ValidationException::withMessages([
                'importe' => 'El importe del comprobante fiscal debe ser mayor a cero.',
            ]);
        }

        return $payload;
    }

    public function url(SaleFiscalDocument $document): string
    {
        $json = json_encode($this->payload($document), JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

        if ($json === false) {
            throw ValidationException::withMessages([
                'qr' => 'No se pudo codificar el payload del QR fiscal.',
            ]);
        }

        return self::BASE_URL.base64_encode($json);
    }

    public function imageDataUri(SaleFiscalDocument $document): string
    {
        $result = (new Builder(
            writer: new PngWriter,
            writerOptions: [],
            validateResult: false,
            data: $this->url($document),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 220,
            margin: 8,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ))->build();

        return $result->getDataUri();
    }

    private function issueDate(SaleFiscalDocument $document): string
    {
        $date = data_get($document->fiscal_payload ?? [], 'voucher_date')
            ?? $document->authorized_at
            ?? $document->sale?->sold_at
            ?? $document->created_at;

        return Carbon::parse($date)->toDateString();
    }

    private function authorizationQrType(string $authorizationType): string
    {
        return match ($authorizationType) {
            SaleFiscalDocument::AUTHORIZATION_CAE => 'E',
            SaleFiscalDocument::AUTHORIZATION_CAEA => 'A',
            default => throw ValidationException::withMessages([
                'authorization_type' => 'El tipo de autorizacion fiscal debe ser CAE o CAEA.',
            ]),
        };
    }

    private function positiveInt(mixed $value, string $label): int
    {
        $number = (int) $value;

        if ($number <= 0) {
            throw ValidationException::withMessages([
                $label => "Falta {$label} para generar el QR fiscal.",
            ]);
        }

        return $number;
    }

    private function digitsAsInt(mixed $value, string $label): int
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if ($digits === '') {
            throw ValidationException::withMessages([
                $label => "Falta {$label} para generar el QR fiscal.",
            ]);
        }

        return (int) $digits;
    }

    /**
     * @param  array<string, mixed>  $customer
     */
    private function receiverDocumentType(array $customer): int
    {
        if (data_get($customer, 'doc_type') !== null) {
            return (int) data_get($customer, 'doc_type', 99);
        }

        return match (strtoupper((string) data_get($customer, 'document_type', 'CONSUMIDOR_FINAL'))) {
            'CUIT' => 80,
            'DNI' => 96,
            default => 99,
        };
    }

    /**
     * @param  array<string, mixed>  $customer
     */
    private function receiverDocumentNumber(array $customer): int
    {
        $number = data_get($customer, 'doc_number')
            ?? data_get($customer, 'document_number')
            ?? 0;

        return (int) preg_replace('/\D+/', '', (string) $number);
    }
}
