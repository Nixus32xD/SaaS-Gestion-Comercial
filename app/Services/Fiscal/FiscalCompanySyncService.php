<?php

namespace App\Services\Fiscal;

use App\Models\BranchFiscalSetting;
use App\Models\Business;
use App\Models\FiscalIdentity;
use Illuminate\Validation\ValidationException;

class FiscalCompanySyncService
{
    public function __construct(
        private readonly FiscalApiClient $client,
        private readonly FiscalSalePayloadBuilder $payloadBuilder,
        private readonly FiscalApiErrorMapper $errorMapper,
    ) {}

    public function syncIdentity(FiscalIdentity $identity): void
    {
        if (! (bool) config('fiscal.enabled')) {
            return;
        }

        try {
            $response = $this->client->upsertCompany([
                'external_fiscal_id' => $identity->external_fiscal_id,
                'cuit' => $identity->cuit,
                'legal_name' => $identity->legal_name ?: 'Identidad fiscal',
                'fiscal_condition' => $identity->fiscal_condition,
                'environment' => $identity->environment,
                'enabled' => true,
                'onboarding_metadata' => ['source' => 'comerstock', 'business_id' => (string) $identity->business_id],
            ]);
        } catch (FiscalApiTimeoutException) {
            throw ValidationException::withMessages(['fiscal_identity' => 'La API fiscal no respondió al guardar la identidad. Intenta nuevamente.']);
        } catch (FiscalApiException $exception) {
            throw ValidationException::withMessages(['fiscal_identity' => $exception->getMessage()]);
        }

        if (($error = $this->apiError($response)) !== null) {
            $mapped = $this->errorMapper->fromResponse($response);
            throw ValidationException::withMessages(['fiscal_identity' => $mapped['message'] ?? $this->friendlyApiErrorMessage($error->code, $error->message)]);
        }
    }

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
            $mappedError = $this->errorMapper->fromResponse($response);

            throw ValidationException::withMessages([
                'fiscal_enabled' => in_array($apiError->code, ['company_not_found', 'company_persist_failed'], true)
                    ? $this->friendlyApiErrorMessage($apiError->code, $apiError->message)
                    : ($mappedError['message'] ?? $this->friendlyApiErrorMessage($apiError->code, $apiError->message)),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function syncFromBranchSettings(
        Business $business,
        BranchFiscalSetting $setting,
        array $payload,
    ): void {
        if (! $this->shouldSyncBranch($setting, $payload)) {
            return;
        }

        $cuit = trim((string) ($payload['fiscal_cuit'] ?? ''));
        if ($cuit === '') {
            throw ValidationException::withMessages([
                'fiscal_cuit' => 'El CUIT fiscal es obligatorio para crear la empresa fiscal externa.',
            ]);
        }

        $externalBusinessId = $this->externalBranchBusinessId($business, $payload);
        $companyPayload = $this->companyPayload($business, $payload, $externalBusinessId, $cuit);
        $currentExternalBusinessId = trim((string) $setting->fiscal_external_business_id);
        $renameFrom = $setting->is_enabled
            && $currentExternalBusinessId !== ''
            && $currentExternalBusinessId !== $externalBusinessId
            ? $currentExternalBusinessId
            : null;

        try {
            $response = $renameFrom !== null
                ? $this->client->upsertCompany($companyPayload, $renameFrom)
                : $this->client->upsertCompany($companyPayload);

            if ($renameFrom !== null && $this->apiError($response)?->code === 'company_not_found') {
                $response = $this->client->upsertCompany($companyPayload);
            }
        } catch (FiscalApiTimeoutException) {
            throw ValidationException::withMessages([
                'is_enabled' => 'La API fiscal no respondió al guardar la configuración de la sucursal. Intenta nuevamente.',
            ]);
        } catch (FiscalApiException $exception) {
            throw ValidationException::withMessages([
                'is_enabled' => $exception->getMessage(),
            ]);
        }

        $apiError = $this->apiError($response);
        if ($apiError !== null) {
            $mappedError = $this->errorMapper->fromResponse($response);

            throw ValidationException::withMessages([
                'is_enabled' => $mappedError['message']
                    ?? $this->friendlyApiErrorMessage($apiError->code, $apiError->message),
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
            || $this->normalizedFiscalValue($business->fiscal_environment)
            !== $this->normalizedFiscalValue($this->apiEnvironment($payload))
            || $this->normalizedFiscalValue($business->fiscal_cuit)
            !== $this->normalizedFiscalValue($payload['fiscal_cuit'] ?? null)
            || $this->normalizedFiscalValue($business->fiscal_condition ?: config('fiscal.defaults.fiscal_condition', 'monotributo'))
            !== $this->normalizedFiscalValue($payload['fiscal_condition'] ?? config('fiscal.defaults.fiscal_condition', 'monotributo'))
            || (int) ($business->fiscal_point_of_sale ?? 0)
            !== (int) ($payload['fiscal_point_of_sale'] ?? 0)
            || $this->normalizedFiscalValue($business->fiscal_document_type)
            !== $this->normalizedFiscalValue($payload['fiscal_document_type'] ?? null)
            || (int) ($business->fiscal_cbte_type ?? 0)
            !== (int) ($payload['fiscal_cbte_type'] ?? 0)
            || (int) ($business->fiscal_concept ?? 0)
            !== (int) ($payload['fiscal_concept'] ?? 0)
            || $this->authorizationMode($business->fiscal_authorization_mode)
            !== $this->authorizationMode($payload['fiscal_authorization_mode'] ?? null)
            || $this->activities($business->fiscal_activities)
            !== $this->activities($payload['fiscal_activities'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function shouldSyncBranch(BranchFiscalSetting $setting, array $payload): bool
    {
        if (! (bool) config('fiscal.enabled') || ! (bool) ($payload['is_enabled'] ?? false)) {
            return false;
        }

        if (! $setting->is_enabled) {
            return true;
        }

        return $this->normalizedFiscalValue($setting->fiscal_external_business_id)
            !== $this->normalizedFiscalValue($payload['fiscal_external_business_id'] ?? null)
            || $this->normalizedFiscalValue($setting->fiscal_environment)
            !== $this->normalizedFiscalValue($this->apiEnvironment($payload))
            || $this->normalizedFiscalValue($setting->fiscal_cuit)
            !== $this->normalizedFiscalValue($payload['fiscal_cuit'] ?? null)
            || $this->normalizedFiscalValue($setting->fiscal_condition)
            !== $this->normalizedFiscalValue($payload['fiscal_condition'] ?? null)
            || (int) ($setting->fiscal_point_of_sale ?? 0)
            !== (int) ($payload['fiscal_point_of_sale'] ?? 0)
            || $this->normalizedFiscalValue($setting->fiscal_document_type)
            !== $this->normalizedFiscalValue($payload['fiscal_document_type'] ?? null)
            || (int) ($setting->fiscal_cbte_type ?? 0)
            !== (int) ($payload['fiscal_cbte_type'] ?? 0)
            || (int) ($setting->fiscal_concept ?? 0)
            !== (int) ($payload['fiscal_concept'] ?? 0)
            || $this->authorizationMode($setting->fiscal_authorization_mode)
            !== $this->authorizationMode($payload['fiscal_authorization_mode'] ?? null)
            || $this->activities($setting->fiscal_activities)
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function externalBranchBusinessId(Business $business, array $payload): string
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
            'fiscal_condition' => $this->fiscalCondition($payload['fiscal_condition'] ?? null),
            'environment' => $this->apiEnvironment($payload),
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
                'authorization_mode' => $this->authorizationMode($payload['fiscal_authorization_mode'] ?? null),
                'activities' => $this->activities($payload['fiscal_activities'] ?? []),
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function apiEnvironment(array $payload): string
    {
        $environment = strtolower(trim((string) ($payload['fiscal_environment'] ?? config('fiscal.environment', 'testing'))));

        return $environment === 'production' ? 'production' : 'testing';
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

    private function authorizationMode(mixed $value): string
    {
        $mode = strtolower(trim((string) $value));

        return in_array($mode, ['cae', 'caea', 'auto'], true)
            ? $mode
            : (string) config('fiscal.defaults.authorization_mode', 'cae');
    }

    private function fiscalCondition(mixed $value): string
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, ['monotributo', 'responsable_inscripto', 'exento'], true)
            ? $value
            : (string) config('fiscal.defaults.fiscal_condition', 'monotributo');
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
