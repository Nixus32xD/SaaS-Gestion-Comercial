<?php

namespace App\Services\Fiscal;

use App\Models\Sale;
use App\Models\SaleFiscalDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LockedFiscalSaleDocumentService extends FiscalSaleDocumentService
{
    public function __construct(
        private readonly FiscalApiClient $lockedClient,
        private readonly FiscalSalePayloadBuilder $lockedPayloadBuilder,
        private readonly FiscalApiErrorMapper $lockedErrorMapper,
    ) {
        parent::__construct($lockedClient, $lockedPayloadBuilder, $lockedErrorMapper);
    }

    public function issue(Sale $sale): SaleFiscalDocument
    {
        // Only reserve the fiscal attempt while holding the row lock. The HTTP
        // call to apiArca is deliberately executed after the transaction commits.
        $document = DB::transaction(function () use ($sale): SaleFiscalDocument {
            $lockedSale = Sale::query()
                ->whereKey($sale->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedSale->loadMissing(['business', 'items.product']);
            $latestDocument = $lockedSale->fiscalDocuments()
                ->orderByDesc('attempt_number')
                ->orderByDesc('id')
                ->first();

            // Revalidate every state-sensitive precondition after obtaining the
            // lock. Checks made before this point would be vulnerable to TOCTOU.
            $this->assertSaleCanReserveFiscalAttempt($lockedSale, $latestDocument);

            $attemptNumber = ((int) $lockedSale->fiscalDocuments()->max('attempt_number')) + 1;
            $idempotencyKey = $this->lockedIdempotencyKey($lockedSale, $attemptNumber);
            $payload = $this->lockedPayloadBuilder->build($lockedSale, $idempotencyKey);

            if (($payload['authorization_type'] ?? null) === SaleFiscalDocument::AUTHORIZATION_CAEA
                && ! filled(data_get($payload, 'caea.code'))) {
                throw ValidationException::withMessages([
                    'fiscal' => 'El comercio esta configurado para CAEA pero no tiene un CAEA vigente cargado.',
                ]);
            }

            return SaleFiscalDocument::query()->create([
                'business_id' => $lockedSale->business_id,
                'sale_id' => $lockedSale->id,
                'attempt_number' => $attemptNumber,
                'fiscal_status' => SaleFiscalDocument::STATUS_PROCESSING,
                'fiscal_point_of_sale' => $payload['point_of_sale'],
                'fiscal_cbte_type' => $payload['cbte_type'] ?? null,
                'fiscal_idempotency_key' => $idempotencyKey,
                'fiscal_payload' => $payload,
                'attempted_at' => now(),
            ]);
        }, 3);

        try {
            $response = $this->lockedClient->createDocument($document->fiscal_payload ?? []);
        } catch (FiscalApiTimeoutException $exception) {
            return $this->lockedMarkUncertain($document, $this->lockedErrorMapper->fromException($exception));
        } catch (FiscalApiException $exception) {
            return $this->lockedMarkError($document, $this->lockedErrorMapper->fromException($exception));
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

        $businessId = $this->lockedPayloadBuilder->externalBusinessId($document->sale->business);

        try {
            if ($document->fiscal_document_id !== null) {
                $response = $this->lockedClient->reconcileDocument(
                    $document->fiscal_document_id,
                    $businessId,
                );
            } else {
                $response = $this->lockedClient->documentByOrigin(
                    $businessId,
                    'sale',
                    $document->sale_id,
                );
            }
        } catch (FiscalApiTimeoutException $exception) {
            return $this->lockedMarkUncertain($document, $this->lockedErrorMapper->fromException($exception));
        } catch (FiscalApiException $exception) {
            return $this->lockedMarkUncertain($document, $this->lockedErrorMapper->fromException($exception));
        }

        return $this->applyResponse($document, $response, reconciling: true);
    }

    private function assertSaleCanReserveFiscalAttempt(Sale $sale, ?SaleFiscalDocument $latestDocument): void
    {
        if ($sale->point_status !== null && $sale->point_status !== Sale::POINT_STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'fiscal' => 'La venta Point no tiene un pago aprobado para emitir el comprobante fiscal.',
            ]);
        }

        if (! (bool) config('fiscal.enabled') || ! $sale->business->hasElectronicBilling()) {
            throw ValidationException::withMessages([
                'fiscal' => 'La facturacion fiscal no esta habilitada para este comercio.',
            ]);
        }

        if ($latestDocument?->isAuthorized()) {
            throw ValidationException::withMessages([
                'fiscal' => 'La venta ya tiene un comprobante fiscal autorizado.',
            ]);
        }

        $canRetry = $latestDocument !== null && $this->lockedErrorMapper->safeToRetry($latestDocument);

        if ($latestDocument?->requiresReconcile() && ! $canRetry) {
            throw ValidationException::withMessages([
                'fiscal' => 'La venta tiene un comprobante fiscal incierto o en proceso. Conciliar antes de reintentar.',
            ]);
        }

        if ($latestDocument !== null && ! $canRetry) {
            throw ValidationException::withMessages([
                'fiscal' => 'El ultimo estado fiscal no es seguro para reintentar. Revisa el detalle fiscal o concilia antes de emitir nuevamente.',
            ]);
        }
    }

    private function lockedIdempotencyKey(Sale $sale, int $attemptNumber): string
    {
        $base = "sale:{$sale->business_id}:{$sale->id}:invoice";

        return $attemptNumber === 1 ? $base : "{$base}:retry:{$attemptNumber}";
    }

    /** @param array<string, mixed> $error */
    private function lockedMarkUncertain(SaleFiscalDocument $document, array $error): SaleFiscalDocument
    {
        $document->update([
            'fiscal_status' => SaleFiscalDocument::STATUS_UNCERTAIN,
            'fiscal_error_code' => $error['code'],
            'fiscal_error_message' => $error['message'],
        ]);

        return $document->refresh();
    }

    /** @param array<string, mixed> $error */
    private function lockedMarkError(SaleFiscalDocument $document, array $error): SaleFiscalDocument
    {
        $document->update([
            'fiscal_status' => SaleFiscalDocument::STATUS_ERROR,
            'fiscal_error_code' => $error['code'],
            'fiscal_error_message' => $error['message'],
        ]);

        return $document->refresh();
    }
}
