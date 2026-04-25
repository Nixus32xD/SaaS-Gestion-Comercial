<?php

namespace App\Services\Fiscal;

use App\Models\Business;
use Illuminate\Validation\ValidationException;

class FiscalCompanySyncService
{
    public function __construct(
        private readonly FiscalApiClient $client,
        private readonly FiscalSalePayloadBuilder $payloadBuilder,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function syncFromBusinessSettings(Business $business, array $payload): void
    {
        if (! $this->shouldSync($business, $payload)) {
            return;
        }

        $cuit = trim((string) ($payload['fiscal_cuit'] ?? ''));

        if ($cuit === '') {
            throw ValidationException::withMessages([
                'fiscal_cuit' => 'El CUIT fiscal es obligatorio para crear la empresa fiscal externa.',
            ]);
        }

        $externalBusinessId = $this->externalBusinessId($business, $payload);
        $companyPayload = $this->companyPayload($business, $payload, $externalBusinessId, $cuit);
        $renameFrom = $this->renameFrom($business, $externalBusinessId);

        try {
            $response = $renameFrom !== null
                ? $this->client->upsertCompany($companyPayload, $renameFrom)
                : $this->client->upsertCompany($companyPayload);

            if ($renameFrom !== null && $this->apiError($response)?->code === 'company_not_found') {
                $response = $this->client->upsertCompany($companyPayload);
            }
        } catch (FiscalApiTimeoutException) {
            throw ValidationException::withMessages([
                'fiscal_enabled' => 'La API fiscal no respondio al crear la empresa fiscal. Intenta nuevamente.',
            ]);
        } catch (FiscalApiException $exception) {
            throw ValidationException::withMessages([
                'fiscal_enabled' => $exception->getMessage(),
            ]);
        }

        $apiError = $this->apiError($response);

        if ($apiError !== null) {
            throw ValidationException::withMessages([
                'fiscal_enabled' => $this->friendlyApiErrorMessage($apiError->code, $apiError->message),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function shouldSync(Business $business, array $payload): bool
    {
        if (! (bool) config('fiscal.enabled') || ! (bool) ($payload['fiscal_enabled'] ?? false)) {
            return false;
        }

        if (! $business->fiscal_enabled) {
            return true;
        }

        return $this->normalizedFiscalValue($business->fiscal_external_business_id)
            !== $this->normalizedFiscalValue($payload['fiscal_external_business_id'] ?? null)
            || $this->normalizedFiscalValue($business->fiscal_cuit)
            !== $this->normalizedFiscalValue($payload['fiscal_cuit'] ?? null)
            || (int) ($business->fiscal_point_of_sale ?? 0)
            !== (int) ($payload['fiscal_point_of_sale'] ?? 0)
            || $this->normalizedFiscalValue($business->fiscal_document_type)
            !== $this->normalizedFiscalValue($payload['fiscal_document_type'] ?? null)
            || (int) ($business->fiscal_cbte_type ?? 0)
            !== (int) ($payload['fiscal_cbte_type'] ?? 0)
            || (int) ($business->fiscal_concept ?? 0)
            !== (int) ($payload['fiscal_concept'] ?? 0)
            || $this->activities($business->fiscal_activities)
            !== $this->activities($payload['fiscal_activities'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function externalBusinessId(Business $business, array $payload): string
    {
        $externalId = trim((string) ($payload['fiscal_external_business_id'] ?? ''));

        return $externalId !== '' ? $externalId : (string) $business->id;
    }

    private function renameFrom(Business $business, string $externalBusinessId): ?string
    {
        if (! $business->fiscal_enabled) {
            return null;
        }

        $currentExternalBusinessId = $this->payloadBuilder->externalBusinessId($business);

        return $currentExternalBusinessId !== $externalBusinessId ? $currentExternalBusinessId : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function companyPayload(
        Business $business,
        array $payload,
        string $externalBusinessId,
        string $cuit
    ): array {
        return [
            'external_business_id' => $externalBusinessId,
            'cuit' => $cuit,
            'legal_name' => trim($business->name) !== '' ? $business->name : 'Comercio '.$business->id,
            'environment' => $this->apiEnvironment(),
            'default_point_of_sale' => $this->intOrDefault(
                $payload['fiscal_point_of_sale'] ?? null,
                (int) config('fiscal.defaults.point_of_sale', 2)
            ),
            'default_voucher_type' => $this->intOrDefault(
                $payload['fiscal_cbte_type'] ?? null,
                (int) config('fiscal.defaults.cbte_type', 11)
            ),
            'enabled' => true,
            'onboarding_metadata' => array_filter([
                'source' => 'saas',
                'business_id' => (string) $business->id,
                'business_slug' => $business->slug,
                'document_type' => trim((string) ($payload['fiscal_document_type'] ?? ''))
                    ?: (string) config('fiscal.defaults.document_type', 'invoice_c'),
                'concept' => $this->intOrDefault(
                    $payload['fiscal_concept'] ?? null,
                    (int) config('fiscal.defaults.concept', 1)
                ),
                'activities' => $this->activities($payload['fiscal_activities'] ?? []),
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
        ];
    }

    private function apiEnvironment(): string
    {
        return config('fiscal.environment') === 'production' ? 'production' : 'testing';
    }

    private function intOrDefault(mixed $value, int $default): int
    {
        $value = (int) $value;

        return $value > 0 ? $value : $default;
    }

    /**
     * @return list<int>
     */
    private function activities(mixed $activities): array
    {
        return collect((array) $activities)
            ->map(fn (mixed $activity): int => (int) $activity)
            ->filter(fn (int $activity): bool => $activity > 0)
            ->values()
            ->all();
    }

    private function normalizedFiscalValue(mixed $value): string
    {
        return trim((string) $value);
    }

    private function apiError(array $response): ?object
    {
        $code = data_get($response, 'error.code')
            ?? data_get($response, 'error_code')
            ?? (data_get($response, 'status') === 'error' ? 'api_error' : null);

        if ($code === null && data_get($response, 'errors') !== null) {
            $code = 'validation';
        }

        if ($code === null) {
            return null;
        }

        return (object) [
            'code' => (string) $code,
            'message' => (string) (
                data_get($response, 'error.message')
                ?? data_get($response, 'message')
                ?? data_get($response, 'error_description')
                ?? ''
            ),
        ];
    }

    private function friendlyApiErrorMessage(string $code, string $message): string
    {
        return match ($code) {
            'company_not_found' => 'La API fiscal no encontro la empresa fiscal anterior para actualizarla y tampoco pudo crear la nueva. Revisa el ID externo del comercio.',
            'company_persist_failed' => 'La API fiscal no pudo guardar la empresa. Revisa si ya existe otro comercio fiscal con el mismo CUIT y ambiente.',
            'validation', 'http_422' => $message !== '' ? $message : 'La API fiscal rechazo los datos de la empresa fiscal.',
            default => $message !== '' ? $message : 'La API fiscal rechazo la creacion de la empresa fiscal.',
        };
    }
}
