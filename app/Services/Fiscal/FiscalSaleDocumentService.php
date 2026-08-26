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
        private readonly FiscalApiErrorMapper $errorMapper,
    ) {}

    public function issue(Sale $sale): SaleFiscalDocument
    {
        $sale->loadMissing(['business', 'items.product', 'latestFiscalDocument']);

        if ($sale->stock_reservation_status === Sale::STOCK_RESERVATION_RESERVED) {
            throw ValidationException::withMessages([
                'fiscal' => 'La venta Point todavia espera la aprobacion del pago.',
            ]);
        }

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

        $latestDocumentCanRetry = $latestDocument !== null && $this->errorMapper->safeToRetry($latestDocument);

        if ($latestDocument?->requiresReconcile() && ! $latestDocumentCanRetry) {
            throw ValidationException::withMessages([
                'fiscal' => 'La venta tiene un comprobante fiscal incierto o en proceso. Conciliar antes de reintentar.',
            ]);
        }

        if ($latestDocument !== null && ! $latestDocumentCanRetry) {
            throw ValidationException::withMessages([
                'fiscal' => 'El ultimo estado fiscal no es seguro para reintentar. Revisa el detalle fiscal o concilia antes de emitir nuevamente.',
            ]);
        }

        $attemptNumber = $this->nextAttemptNumber($sale);
        $idempotencyKey = $this->idempotencyKey($sale, $attemptNumber);
        $payload = $this->payloadBuilder->build($sale, $idempotencyKey);

        if (($payload['authorization_type'] ?? null) === SaleFiscalDocument::AUTHORIZATION_CAEA
            && ! filled(data_get($payload, 'caea.code'))) {
            throw ValidationException::withMessages([
                'fiscal' => 'El comercio esta configurado para CAEA pero no tiene un CAEA vigente cargado.',
            ]);
        }

        $document = SaleFiscalDocument::query()->create([
            'business_id' => $sale->business_id,
            'sale_id' => $sale->id,
            'attempt_number' => $attemptNumber,
            'fiscal_status' => SaleFiscalDocument::STATUS_PROCESSING,
            'fiscal_point_of_sale' => $payload['point_of_sale'],
            'fiscal_cbte_type' => $payload['cbte_type'] ?? null,
            'fiscal_idempotency_key' => $idempotencyKey,
            'fiscal_payload' => $payload,
            'attempted_at' => now(),
        ]);

        try {
            $response = $this->client->createDocument($payload);
        } catch (FiscalApiTimeoutException $exception) {
            return $this->markUncertain($document, $this->errorMapper->fromException($exception));
        } catch (FiscalApiException $exception) {
            return $this->markError($document, $this->errorMapper->fromException($exception));
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
            return $this->markUncertain($document, $this->errorMapper->fromException($exception));
        } catch (FiscalApiException $exception) {
            return $this->markUncertain($document, $this->errorMapper->fromException($exception));
        }

        return $this->applyResponse($document, $response, reconciling: true);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function applyResponse(
        SaleFiscalDocument $document,
        array $response,
        bool $reconciling = false
    ): SaleFiscalDocument {
        $payload = $this->documentPayload($response);
        $status = $this->normalizeStatus((string) data_get($payload, 'status', SaleFiscalDocument::STATUS_ERROR));
        $apiError = $this->errorMapper->fromResponse($response);
        $authorizationType = $this->authorizationType($payload, $document);
        $authorizationCode = $this->authorizationCode($payload, $authorizationType, $document);
        $authorizationExpiresAt = $this->authorizationExpiresAt($payload, $authorizationType, $document);

        if ($apiError !== null) {
            if ((bool) $apiError['requires_reconcile']
                || ($reconciling
                    && $status === SaleFiscalDocument::STATUS_ERROR
                    && $apiError['code'] !== 'document_without_number')) {
                $status = SaleFiscalDocument::STATUS_UNCERTAIN;
            } elseif ($status === SaleFiscalDocument::STATUS_AUTHORIZED) {
                $status = SaleFiscalDocument::STATUS_ERROR;
            }
        }

        $document->fill([
            'fiscal_document_id' => data_get($payload, 'id')
                ?? data_get($payload, 'document_id')
                ?? data_get($payload, 'fiscal_document_id')
                ?? $document->fiscal_document_id,
            'fiscal_status' => $status,
            'fiscal_point_of_sale' => data_get($payload, 'point_of_sale', $document->fiscal_point_of_sale),
            'fiscal_cbte_type' => data_get($payload, 'cbte_type', $document->fiscal_cbte_type),
            'fiscal_number' => data_get($payload, 'number', $document->fiscal_number),
            'fiscal_cae' => $this->legacyCae($payload, $authorizationType, $authorizationCode, $document),
            'fiscal_cae_expires_at' => $this->legacyCaeExpiresAt(
                $payload,
                $authorizationType,
                $authorizationExpiresAt,
                $document
            ),
            'authorization_type' => $authorizationType,
            'authorization_code' => $authorizationCode,
            'authorization_expires_at' => $authorizationExpiresAt,
            'caea_period' => data_get($payload, 'caea_period')
                ?? data_get($payload, 'caea.period')
                ?? $document->caea_period,
            'caea_order' => data_get($payload, 'caea_order')
                ?? data_get($payload, 'caea.order')
                ?? $document->caea_order,
            'caea_report_status' => data_get($payload, 'caea_report_status')
                ?? data_get($payload, 'caea.report_status')
                ?? $this->defaultCaeaReportStatus($payload, $status, $authorizationType, $document),
            'caea_reported_at' => $this->dateTimeOrNull(
                data_get($payload, 'caea_reported_at') ?? data_get($payload, 'caea.reported_at')
            ) ?? ($this->isReportedCaea($payload, $authorizationType) ? now() : $document->caea_reported_at),
            'fiscal_error_code' => $apiError['code'] ?? null,
            'fiscal_error_message' => $apiError['message'] ?? null,
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

    /**
     * @param  array<string, mixed>  $error
     */
    private function markUncertain(SaleFiscalDocument $document, array $error): SaleFiscalDocument
    {
        $document->update([
            'fiscal_status' => SaleFiscalDocument::STATUS_UNCERTAIN,
            'fiscal_error_code' => $error['code'],
            'fiscal_error_message' => $error['message'],
        ]);

        return $document->refresh();
    }

    /**
     * @param  array<string, mixed>  $error
     */
    private function markError(SaleFiscalDocument $document, array $error): SaleFiscalDocument
    {
        $document->update([
            'fiscal_status' => SaleFiscalDocument::STATUS_ERROR,
            'fiscal_error_code' => $error['code'],
            'fiscal_error_message' => $error['message'],
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

    private function dateTimeOrNull(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function authorizationType(array $payload, SaleFiscalDocument $document): ?string
    {
        $value = data_get($payload, 'authorization_type')
            ?? data_get($payload, 'authorization.type')
            ?? data_get($payload, 'auth_type')
            ?? data_get($payload, 'caea.type')
            ?? $document->authorization_type;

        if ($value === null) {
            if (data_get($payload, 'caea') !== null || data_get($payload, 'caea_code') !== null) {
                return SaleFiscalDocument::AUTHORIZATION_CAEA;
            }

            if (data_get($payload, 'cae') !== null || $document->fiscal_cae !== null) {
                return SaleFiscalDocument::AUTHORIZATION_CAE;
            }

            return null;
        }

        $value = strtoupper(trim((string) $value));

        return in_array($value, [
            SaleFiscalDocument::AUTHORIZATION_CAE,
            SaleFiscalDocument::AUTHORIZATION_CAEA,
        ], true) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function authorizationCode(
        array $payload,
        ?string $authorizationType,
        SaleFiscalDocument $document
    ): ?string {
        $value = data_get($payload, 'authorization_code')
            ?? data_get($payload, 'authorization.code')
            ?? data_get($payload, 'auth_code');

        if ($value === null) {
            $value = $authorizationType === SaleFiscalDocument::AUTHORIZATION_CAEA
                ? (data_get($payload, 'caea') ?? data_get($payload, 'caea_code'))
                : data_get($payload, 'cae');
        }

        if ($value === null) {
            return $document->authorization_code;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function authorizationExpiresAt(
        array $payload,
        ?string $authorizationType,
        SaleFiscalDocument $document
    ): ?string {
        $value = data_get($payload, 'authorization_expires_at')
            ?? data_get($payload, 'authorization.expires_at')
            ?? data_get($payload, 'auth_expires_at');

        if ($value === null) {
            $value = $authorizationType === SaleFiscalDocument::AUTHORIZATION_CAEA
                ? (data_get($payload, 'caea_expires_at') ?? data_get($payload, 'caea.expires_at'))
                : data_get($payload, 'cae_expires_at');
        }

        return $this->dateOrNull($value) ?? $document->authorization_expires_at?->toDateString();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function legacyCae(
        array $payload,
        ?string $authorizationType,
        ?string $authorizationCode,
        SaleFiscalDocument $document
    ): ?string {
        $cae = data_get($payload, 'cae');

        if ($cae !== null && trim((string) $cae) !== '') {
            return (string) $cae;
        }

        return $authorizationType === SaleFiscalDocument::AUTHORIZATION_CAE
            ? $authorizationCode
            : $document->fiscal_cae;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function legacyCaeExpiresAt(
        array $payload,
        ?string $authorizationType,
        ?string $authorizationExpiresAt,
        SaleFiscalDocument $document
    ): ?string {
        $caeExpiresAt = $this->dateOrNull(data_get($payload, 'cae_expires_at'));

        if ($caeExpiresAt !== null) {
            return $caeExpiresAt;
        }

        return $authorizationType === SaleFiscalDocument::AUTHORIZATION_CAE
            ? $authorizationExpiresAt
            : $document->fiscal_cae_expires_at?->toDateString();
    }

    private function defaultCaeaReportStatus(
        array $payload,
        string $status,
        ?string $authorizationType,
        SaleFiscalDocument $document
    ): ?string {
        if ($authorizationType !== SaleFiscalDocument::AUTHORIZATION_CAEA) {
            return null;
        }

        $apiFiscalStatus = data_get($payload, 'fiscal_status');

        if ($apiFiscalStatus === SaleFiscalDocument::CAEA_REPORT_REPORTED) {
            return SaleFiscalDocument::CAEA_REPORT_REPORTED;
        }

        if ($document->caea_report_status !== null) {
            return $document->caea_report_status;
        }

        return $status === SaleFiscalDocument::STATUS_AUTHORIZED
            ? SaleFiscalDocument::CAEA_REPORT_PENDING
            : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isReportedCaea(array $payload, ?string $authorizationType): bool
    {
        return $authorizationType === SaleFiscalDocument::AUTHORIZATION_CAEA
            && (data_get($payload, 'fiscal_status') === SaleFiscalDocument::CAEA_REPORT_REPORTED
                || data_get($payload, 'caea.report_status') === SaleFiscalDocument::CAEA_REPORT_REPORTED);
    }
}
