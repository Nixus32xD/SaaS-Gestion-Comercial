<?php

namespace App\Services\Fiscal;

use App\Models\Business;
use App\Models\BusinessFiscalCredential;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FiscalCredentialOnboardingService
{
    public function __construct(
        private readonly FiscalApiClient $client,
        private readonly FiscalSalePayloadBuilder $payloadBuilder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(Business $business, string $externalBusinessId, ?User $user = null): array
    {
        $credential = $this->credentialForCompany($business, $externalBusinessId);

        return [
            'can_manage' => $user?->isBusinessAdmin() ?? false,
            'status' => $credential?->status ?? 'sin_configurar',
            'status_label' => $this->statusLabel($credential?->status),
            'fiscal_cuit' => $business->fiscal_cuit,
            'defaults' => [
                'key_name' => $this->defaultKeyName($externalBusinessId, $business),
                'common_name' => $externalBusinessId,
                'organization_name' => $business->name,
                'country_name' => 'AR',
            ],
            'credential' => $credential ? $this->credentialPayload($credential) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function generateCsr(Business $business, User $user, array $payload): BusinessFiscalCredential
    {
        $externalBusinessId = $this->payloadBuilder->externalBusinessId($business);

        try {
            $response = $this->client->generateCredentialCsr($externalBusinessId, [
                'key_name' => $payload['key_name'],
                'common_name' => $payload['common_name'],
                'organization_name' => $payload['organization_name'],
                'country_name' => $payload['country_name'],
                'metadata' => array_filter([
                    'source' => 'saas',
                    'requested_by_user_id' => (string) $user->id,
                    'fiscal_cuit' => $business->fiscal_cuit,
                ], fn (mixed $value): bool => $value !== null && $value !== ''),
            ]);
        } catch (FiscalApiTimeoutException $exception) {
            throw ValidationException::withMessages([
                'key_name' => 'La API fiscal no respondio al generar el CSR. Intenta nuevamente.',
            ]);
        } catch (FiscalApiException $exception) {
            throw ValidationException::withMessages([
                'key_name' => $exception->getMessage(),
            ]);
        }

        $this->throwIfApiError($response, 'No se pudo generar el CSR fiscal.', 'key_name');

        $data = $this->payloadData($response);
        $credential = data_get($data, 'credential');
        $csr = data_get($data, 'csr');

        if (! is_array($credential) || ! is_string($csr) || trim($csr) === '') {
            throw ValidationException::withMessages([
                'key_name' => 'La API fiscal no devolvio un CSR valido.',
            ]);
        }

        return $this->persistCredential($business, $data, $credential, $csr);
    }

    public function uploadCertificate(
        Business $business,
        BusinessFiscalCredential $credential,
        User $user,
        string $certificate
    ): BusinessFiscalCredential {
        $this->assertCredentialBelongsToBusiness($business, $credential);

        if ($credential->fiscal_credential_id === null || $credential->fiscal_credential_id === '') {
            throw ValidationException::withMessages([
                'certificate' => 'Primero genera el CSR para esta credencial.',
            ]);
        }

        $externalBusinessId = $this->credentialCompany($business, $credential);

        try {
            $response = $this->client->uploadCredentialCertificate($externalBusinessId, $credential->fiscal_credential_id, [
                'certificate' => $certificate,
                'active' => true,
                'metadata' => [
                    'source' => 'saas',
                    'uploaded_by_user_id' => (string) $user->id,
                ],
            ]);
        } catch (FiscalApiTimeoutException $exception) {
            $this->markCredentialError($credential, 'timeout', 'La API fiscal no respondio al cargar el certificado.');

            throw ValidationException::withMessages([
                'certificate' => 'La API fiscal no respondio al cargar el certificado. Intenta nuevamente.',
            ]);
        } catch (FiscalApiException $exception) {
            $this->markCredentialError($credential, 'configuration_error', $exception->getMessage());

            throw ValidationException::withMessages([
                'certificate' => $exception->getMessage(),
            ]);
        }

        $apiError = $this->apiError($response);
        if ($apiError !== null) {
            $message = $this->friendlyApiErrorMessage($apiError['code'], $apiError['message']);
            $this->markCredentialError($credential, $apiError['code'], $message);

            throw ValidationException::withMessages([
                'certificate' => $message,
            ]);
        }

        $data = $this->payloadData($response);
        $remoteCredential = data_get($data, 'credential');

        if (! is_array($remoteCredential)) {
            throw ValidationException::withMessages([
                'certificate' => 'La API fiscal no devolvio el estado de la credencial.',
            ]);
        }

        $credential->fill([
            'fiscal_business_id' => data_get($data, 'business_id', $credential->fiscal_business_id),
            'fiscal_credential_id' => (string) data_get($remoteCredential, 'id', $credential->fiscal_credential_id),
            'key_name' => (string) data_get($remoteCredential, 'key_name', $credential->key_name),
            'status' => $this->normalizeCredentialStatus(
                data_get($remoteCredential, 'status'),
                (bool) data_get($remoteCredential, 'active', false)
            ),
            'certificate_expires_at' => data_get($remoteCredential, 'certificate_expires_at'),
            'last_error_code' => null,
            'last_error_message' => null,
            'metadata' => data_get($remoteCredential, 'metadata', $credential->metadata),
        ]);
        $credential->save();

        return $credential->refresh();
    }

    public function testCredentials(Business $business, BusinessFiscalCredential $credential): BusinessFiscalCredential
    {
        $this->assertCredentialBelongsToBusiness($business, $credential);

        $externalBusinessId = $this->credentialCompany($business, $credential);

        try {
            $response = $this->client->testCredentials($externalBusinessId);
        } catch (FiscalApiTimeoutException $exception) {
            $this->markCredentialTest($credential, 'error', 'timeout', 'La API fiscal no respondio al probar las credenciales.');

            throw ValidationException::withMessages([
                'test' => 'La API fiscal no respondio al probar las credenciales. Intenta nuevamente.',
            ]);
        } catch (FiscalApiException $exception) {
            $this->markCredentialTest($credential, 'error', 'configuration_error', $exception->getMessage());

            throw ValidationException::withMessages([
                'test' => $exception->getMessage(),
            ]);
        }

        $apiError = $this->apiError($response);
        if ($apiError !== null) {
            $message = $this->friendlyApiErrorMessage($apiError['code'], $apiError['message']);
            $this->markCredentialTest($credential, 'error', $apiError['code'], $message);

            throw ValidationException::withMessages([
                'test' => $message,
            ]);
        }

        $credential->fill([
            'status' => BusinessFiscalCredential::STATUS_ACTIVE,
            'last_test_status' => 'ok',
            'last_tested_at' => now(),
            'last_error_code' => null,
            'last_error_message' => null,
        ]);
        $credential->save();

        return $credential->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function credentialPayload(BusinessFiscalCredential $credential): array
    {
        return [
            'id' => $credential->id,
            'fiscal_credential_id' => $credential->fiscal_credential_id,
            'fiscal_business_id' => $credential->fiscal_business_id,
            'key_name' => $credential->key_name,
            'status' => $credential->status,
            'status_label' => $this->statusLabel($credential->status),
            'csr' => $credential->csr,
            'certificate_expires_at' => $credential->certificate_expires_at?->toIso8601String(),
            'last_error_code' => $credential->last_error_code,
            'last_error_message' => $credential->last_error_message,
            'last_test_status' => $credential->last_test_status,
            'last_tested_at' => $credential->last_tested_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $remoteCredential
     */
    private function persistCredential(
        Business $business,
        array $data,
        array $remoteCredential,
        string $csr
    ): BusinessFiscalCredential {
        $fiscalCredentialId = (string) data_get($remoteCredential, 'id');
        $keyName = (string) data_get($remoteCredential, 'key_name');

        if ($fiscalCredentialId === '' || $keyName === '') {
            throw ValidationException::withMessages([
                'key_name' => 'La API fiscal no devolvio los datos de la credencial.',
            ]);
        }

        /** @var BusinessFiscalCredential $credential */
        $credential = BusinessFiscalCredential::query()->updateOrCreate(
            [
                'business_id' => $business->id,
                'key_name' => $keyName,
            ],
            [
                'fiscal_business_id' => data_get($data, 'business_id'),
                'fiscal_credential_id' => $fiscalCredentialId,
                'status' => $this->normalizeCredentialStatus(
                    data_get($remoteCredential, 'status'),
                    (bool) data_get($remoteCredential, 'active', false)
                ),
                'csr' => $csr,
                'certificate_expires_at' => data_get($remoteCredential, 'certificate_expires_at'),
                'last_error_code' => null,
                'last_error_message' => null,
                'metadata' => data_get($remoteCredential, 'metadata'),
            ]
        );

        return $credential->refresh();
    }

    private function normalizeCredentialStatus(mixed $status, bool $active): string
    {
        if ($active) {
            return BusinessFiscalCredential::STATUS_ACTIVE;
        }

        $status = is_string($status) ? $status : BusinessFiscalCredential::STATUS_PENDING_CERTIFICATE;

        return in_array($status, [
            BusinessFiscalCredential::STATUS_PENDING_CERTIFICATE,
            BusinessFiscalCredential::STATUS_CERTIFICATE_UPLOADED,
            BusinessFiscalCredential::STATUS_ACTIVE,
            BusinessFiscalCredential::STATUS_ERROR,
        ], true) ? $status : BusinessFiscalCredential::STATUS_PENDING_CERTIFICATE;
    }

    private function defaultKeyName(string $externalBusinessId, Business $business): string
    {
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', mb_strtolower($externalBusinessId)) ?: '';
        $base = trim($base, '.-_');

        if ($base === '') {
            $base = Str::slug($business->slug ?: $business->name) ?: 'empresa-'.$business->id;
        }

        return str_ends_with($base, '.key') ? $base : "{$base}.key";
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            BusinessFiscalCredential::STATUS_PENDING_CERTIFICATE => 'CSR generado',
            BusinessFiscalCredential::STATUS_CERTIFICATE_UPLOADED => 'Certificado cargado',
            BusinessFiscalCredential::STATUS_ACTIVE => 'Activo',
            BusinessFiscalCredential::STATUS_ERROR => 'Error',
            default => 'Sin configurar',
        };
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function payloadData(array $response): array
    {
        $data = data_get($response, 'data');

        return is_array($data) ? $data : $response;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function throwIfApiError(array $response, string $fallback, string $field): void
    {
        $apiError = $this->apiError($response);

        if ($apiError === null) {
            return;
        }

        throw ValidationException::withMessages([
            $field => $this->friendlyApiErrorMessage($apiError['code'], $apiError['message'] ?: $fallback),
        ]);
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array{code: string, message: string}|null
     */
    private function apiError(array $response): ?array
    {
        $code = data_get($response, 'error.code')
            ?? data_get($response, 'error_code')
            ?? (data_get($response, 'status') === 'error' ? 'api_error' : null);

        $message = data_get($response, 'error.message')
            ?? data_get($response, 'message')
            ?? data_get($response, 'error_description')
            ?? '';

        if ($code === null && data_get($response, 'errors') !== null) {
            $code = 'validation';
            $message = 'La API fiscal rechazo los datos enviados.';
        }

        if ($code === null) {
            return null;
        }

        return [
            'code' => (string) $code,
            'message' => (string) $message,
        ];
    }

    private function friendlyApiErrorMessage(string $code, string $message): string
    {
        return match ($code) {
            'company_not_found' => 'La API fiscal no encontro la empresa fiscal configurada. Revisa que el ID externo del comercio exista en la API fiscal y coincida con el CSR generado.',
            'arca_http_error' => 'ARCA respondio con un error interno al probar las credenciales. Intenta nuevamente en unos minutos; si persiste, revisa en la API fiscal los logs de WSAA/WSFE y que el certificado este delegado al web service correcto.',
            'certificate_private_key_mismatch' => 'El certificado .crt no corresponde al CSR/key generado para esta credencial.',
            'certificate_expired' => 'El certificado .crt esta vencido.',
            'private_key_invalid' => 'La API fiscal no pudo abrir la key privada generada. Genera una credencial nueva.',
            'validation' => 'Los datos enviados a la API fiscal no son validos.',
            default => $message !== '' ? $message : 'La API fiscal rechazo la operacion.',
        };
    }

    private function credentialCompany(Business $business, BusinessFiscalCredential $credential): string
    {
        $credentialBusinessId = trim((string) $credential->fiscal_business_id);

        return $credentialBusinessId !== ''
            ? $credentialBusinessId
            : $this->payloadBuilder->externalBusinessId($business);
    }

    private function credentialForCompany(Business $business, string $externalBusinessId): ?BusinessFiscalCredential
    {
        return BusinessFiscalCredential::query()
            ->forBusiness($business->id)
            ->where(function ($query) use ($externalBusinessId): void {
                $query
                    ->where('fiscal_business_id', $externalBusinessId)
                    ->orWhereNull('fiscal_business_id')
                    ->orWhere('fiscal_business_id', '');
            })
            ->latest('id')
            ->first();
    }

    private function assertCredentialBelongsToBusiness(Business $business, BusinessFiscalCredential $credential): void
    {
        if ($credential->business_id !== $business->id) {
            abort(403);
        }
    }

    private function markCredentialError(BusinessFiscalCredential $credential, string $code, string $message): void
    {
        $credential->update([
            'status' => BusinessFiscalCredential::STATUS_ERROR,
            'last_error_code' => $code,
            'last_error_message' => $message,
        ]);
    }

    private function markCredentialTest(
        BusinessFiscalCredential $credential,
        string $status,
        string $code,
        string $message
    ): void {
        $credential->update([
            'last_test_status' => $status,
            'last_tested_at' => now(),
            'last_error_code' => $code,
            'last_error_message' => $message,
        ]);
    }
}
