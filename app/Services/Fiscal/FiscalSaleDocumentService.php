<?php

namespace App\Services\Fiscal;

use App\Models\Sale;
use App\Models\SaleFiscalDocument;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class FiscalSaleDocumentService
{
    public function __construct(
        private readonly FiscalApiClient $client,
        private readonly FiscalSalePayloadBuilder $payloadBuilder,
    ) {}

    public function issue(Sale $sale): SaleFiscalDocument
    {
        $sale->loadMissing(['business', 'items.product', 'latestFiscalDocument']);

        $business = $sale->business;
        if (! (bool) config('fiscal.enabled') || ! $business->hasElectronicBilling()) {
            throw ValidationException::withMessages([
                'fiscal' => 'La facturacion fiscal no esta habilitada para este comercio.',
            ]);
        }

        $latestDocument = $sale->latestFiscalDocument;

        if ($latestDocument?->isAuthorized()) {
            throw ValidationException::withMessages([
                'fiscal' => 'La venta ya tiene un comprobante fiscal autorizado.',
            ]);
        }

        if ($latestDocument?->requiresReconcile()) {
            throw ValidationException::withMessages([
                'fiscal' => 'La venta tiene un comprobante fiscal incierto o en proceso. Conciliar antes de reintentar.',
            ]);
        }

        $attemptNumber = $this->nextAttemptNumber($sale);
        $idempotencyKey = $this->idempotencyKey($sale, $attemptNumber);
        $payload = $this->payloadBuilder->build($sale, $idempotencyKey);

        $document = SaleFiscalDocument::query()->create([
            'business_id' => $sale->business_id,
            'sale_id' => $sale->id,
            'attempt_number' => $attemptNumber,
            'fiscal_status' => SaleFiscalDocument::STATUS_PROCESSING,
            'fiscal_point_of_sale' => $payload['point_of_sale'],
            'fiscal_cbte_type' => $payload['cbte_type'],
            'fiscal_idempotency_key' => $idempotencyKey,
            'fiscal_payload' => $payload,
            'attempted_at' => now(),
        ]);

        try {
            $response = $this->client->createDocument($payload);
        } catch (FiscalApiTimeoutException $exception) {
            return $this->markUncertain($document, $exception->getMessage());
        } catch (FiscalApiException $exception) {
            return $this->markError($document, 'configuration_error', $exception->getMessage());
        }

        return $this->applyResponse($document, $response);
    }

    public function reconcile(SaleFiscalDocument $document): SaleFiscalDocument
    {
        $document->loadMissing(['sale.business']);

        if (! $document->requiresReconcile()) {
            throw ValidationException::withMessages([
                'fiscal' => 'Solo se pueden conciliar comprobantes inciertos o en proceso.',
            ]);
        }

        try {
            if ($document->fiscal_document_id !== null) {
                $response = $this->client->reconcileDocument($document->fiscal_document_id);
            } else {
                $businessId = $this->payloadBuilder->externalBusinessId($document->sale->business);
                $response = $this->client->documentByOrigin($businessId, 'sale', $document->sale_id);
            }
        } catch (FiscalApiTimeoutException $exception) {
            return $this->markUncertain($document, $exception->getMessage());
        } catch (FiscalApiException $exception) {
            return $this->markError($document, 'configuration_error', $exception->getMessage());
        }

        return $this->applyResponse($document, $response);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function applyResponse(SaleFiscalDocument $document, array $response): SaleFiscalDocument
    {
        $payload = $this->documentPayload($response);
        $status = $this->normalizeStatus((string) data_get($payload, 'status', SaleFiscalDocument::STATUS_ERROR));

        $document->fill([
            'fiscal_document_id' => data_get($payload, 'id')
                ?? data_get($payload, 'document_id')
                ?? data_get($payload, 'fiscal_document_id')
                ?? $document->fiscal_document_id,
            'fiscal_status' => $status,
            'fiscal_point_of_sale' => data_get($payload, 'point_of_sale', $document->fiscal_point_of_sale),
            'fiscal_cbte_type' => data_get($payload, 'cbte_type', $document->fiscal_cbte_type),
            'fiscal_number' => data_get($payload, 'number', $document->fiscal_number),
            'fiscal_cae' => data_get($payload, 'cae', $document->fiscal_cae),
            'fiscal_cae_expires_at' => $this->dateOrNull(data_get($payload, 'cae_expires_at')),
            'fiscal_error_code' => data_get($payload, 'error.code'),
            'fiscal_error_message' => data_get($payload, 'error.message'),
            'fiscal_response' => $response,
            'fiscal_observations' => data_get($payload, 'observations'),
            'authorized_at' => $status === SaleFiscalDocument::STATUS_AUTHORIZED ? now() : null,
        ]);

        $document->save();

        return $document->refresh();
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function documentPayload(array $response): array
    {
        $data = data_get($response, 'data');

        if (! is_array($data)) {
            return $response;
        }

        if (array_is_list($data)) {
            $first = $data[0] ?? [];

            return is_array($first) ? $first : $response;
        }

        return $data;
    }

    private function markUncertain(SaleFiscalDocument $document, string $message): SaleFiscalDocument
    {
        $document->update([
            'fiscal_status' => SaleFiscalDocument::STATUS_UNCERTAIN,
            'fiscal_error_code' => 'timeout',
            'fiscal_error_message' => $message !== '' ? $message : 'La API fiscal no respondio dentro del tiempo esperado.',
        ]);

        return $document->refresh();
    }

    private function markError(SaleFiscalDocument $document, string $code, string $message): SaleFiscalDocument
    {
        $document->update([
            'fiscal_status' => SaleFiscalDocument::STATUS_ERROR,
            'fiscal_error_code' => $code,
            'fiscal_error_message' => $message,
        ]);

        return $document->refresh();
    }

    private function nextAttemptNumber(Sale $sale): int
    {
        return ((int) $sale->fiscalDocuments()->max('attempt_number')) + 1;
    }

    private function idempotencyKey(Sale $sale, int $attemptNumber): string
    {
        $base = "sale:{$sale->business_id}:{$sale->id}:invoice";

        return $attemptNumber === 1 ? $base : "{$base}:retry:{$attemptNumber}";
    }

    private function normalizeStatus(string $status): string
    {
        return in_array($status, [
            SaleFiscalDocument::STATUS_AUTHORIZED,
            SaleFiscalDocument::STATUS_REJECTED,
            SaleFiscalDocument::STATUS_ERROR,
            SaleFiscalDocument::STATUS_UNCERTAIN,
            SaleFiscalDocument::STATUS_PROCESSING,
        ], true) ? $status : SaleFiscalDocument::STATUS_ERROR;
    }

    private function dateOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }
}
