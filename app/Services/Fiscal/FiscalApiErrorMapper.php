<?php

namespace App\Services\Fiscal;

use App\Models\SaleFiscalDocument;

class FiscalApiErrorMapper
{
    public const CATEGORY_ARCA_INFRASTRUCTURE = 'arca_infrastructure';

    public const CATEGORY_TIMEOUT = 'timeout';

    public const CATEGORY_AUTHENTICATION = 'authentication';

    public const CATEGORY_VALIDATION = 'validation';

    public const CATEGORY_NUMBERING = 'numbering';

    public const CATEGORY_DUPLICATED = 'duplicated';

    public const CATEGORY_UNKNOWN = 'unknown';

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>|null
     */
    public function fromResponse(array $response): ?array
    {
        $code = $this->firstString($response, [
            'error.code',
            'data.error.code',
            'error_code',
            'data.error_code',
            'code',
            'data.code',
            'errors.0.code',
            'data.errors.0.code',
            'Errors.Err.Code',
            'data.Errors.Err.Code',
        ]);
        $technicalMessage = $this->firstString($response, [
            'error.technical_message',
            'data.error.technical_message',
            'technical_message',
            'data.technical_message',
            'error.detail',
            'data.error.detail',
            'error.message',
            'data.error.message',
            'message',
            'data.message',
            'error_description',
            'data.error_description',
            'errors.0.message',
            'data.errors.0.message',
            'Errors.Err.Msg',
            'data.Errors.Err.Msg',
        ]);
        $status = data_get($response, 'status')
            ?? data_get($response, 'data.status')
            ?? data_get($response, 'http_status')
            ?? data_get($response, 'status_code');
        $httpStatus = $this->httpStatus($response);
        $hasError = $code !== null
            || $technicalMessage !== null
            || (is_string($status) && in_array(strtolower($status), ['error', 'rejected'], true))
            || ($httpStatus !== null && $httpStatus >= 400);

        if (! $hasError) {
            return null;
        }

        $category = $this->category($code, $technicalMessage, $status, $httpStatus);
        $retryable = $this->boolValue(data_get($response, 'retryable') ?? data_get($response, 'error.retryable'));
        $requiresReconcile = $this->boolValue(
            data_get($response, 'requires_reconcile') ?? data_get($response, 'error.requires_reconcile')
        );

        if ($retryable === null) {
            $retryable = $this->defaultRetryable($category, $status);
        }

        if ($requiresReconcile === null) {
            $requiresReconcile = $this->defaultRequiresReconcile($category, $status);
        }

        $technicalMessage = trim((string) ($technicalMessage ?? ''));
        $code = trim((string) ($code ?? ($httpStatus !== null ? "http_{$httpStatus}" : 'api_error')));

        return [
            'code' => $code,
            'message' => $this->userMessage($category, $technicalMessage),
            'technical_message' => $technicalMessage,
            'status' => $status ?? $httpStatus ?? 'error',
            'retryable' => $retryable,
            'requires_reconcile' => $requiresReconcile,
            'category' => $category,
            'action' => $this->action($category, $requiresReconcile, $retryable),
            'http_status' => $httpStatus,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fromException(FiscalApiException $exception): array
    {
        $category = $exception instanceof FiscalApiTimeoutException
            ? self::CATEGORY_TIMEOUT
            : $this->category('configuration_error', $exception->getMessage(), 'error', null);

        return [
            'code' => $exception instanceof FiscalApiTimeoutException ? 'timeout' : 'configuration_error',
            'message' => $this->userMessage($category, $exception->getMessage()),
            'technical_message' => $exception->getMessage(),
            'status' => 'error',
            'retryable' => false,
            'requires_reconcile' => $category === self::CATEGORY_TIMEOUT,
            'category' => $category,
            'action' => $this->action($category, $category === self::CATEGORY_TIMEOUT, false),
            'http_status' => null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fromDocument(SaleFiscalDocument $document): ?array
    {
        $response = is_array($document->fiscal_response) ? $document->fiscal_response : [];
        $mapped = $response !== [] ? $this->fromResponse($response) : null;

        if ($mapped !== null) {
            $mapped['code'] = $document->fiscal_error_code ?: $mapped['code'];
            $mapped['message'] = $document->fiscal_error_message ?: $mapped['message'];

            return $mapped;
        }

        if ($document->fiscal_error_code === null && $document->fiscal_error_message === null) {
            if ($document->requiresReconcile()) {
                return [
                    'code' => $document->fiscal_status,
                    'message' => 'El estado del comprobante no esta confirmado. Usa Conciliar para consultar el estado real antes de emitir otra vez.',
                    'technical_message' => '',
                    'status' => $document->fiscal_status,
                    'retryable' => false,
                    'requires_reconcile' => true,
                    'category' => self::CATEGORY_UNKNOWN,
                    'action' => 'conciliar',
                    'http_status' => null,
                ];
            }

            return null;
        }

        $category = $this->category(
            $document->fiscal_error_code,
            $document->fiscal_error_message,
            $document->fiscal_status,
            null
        );
        $requiresReconcile = $document->requiresReconcile()
            || $this->defaultRequiresReconcile($category, $document->fiscal_status);
        $retryable = $this->defaultRetryable($category, $document->fiscal_status) && ! $requiresReconcile;

        return [
            'code' => $document->fiscal_error_code ?? $document->fiscal_status,
            'message' => $document->fiscal_error_message ?? $this->userMessage($category, ''),
            'technical_message' => $document->fiscal_error_message ?? '',
            'status' => $document->fiscal_status,
            'retryable' => $retryable,
            'requires_reconcile' => $requiresReconcile,
            'category' => $category,
            'action' => $this->action($category, $requiresReconcile, $retryable),
            'http_status' => null,
        ];
    }

    public function safeToRetry(SaleFiscalDocument $document): bool
    {
        if (! in_array($document->fiscal_status, [
            SaleFiscalDocument::STATUS_REJECTED,
            SaleFiscalDocument::STATUS_ERROR,
        ], true)) {
            return false;
        }

        $mapped = $this->fromDocument($document);

        if ($mapped === null) {
            return $document->fiscal_status === SaleFiscalDocument::STATUS_REJECTED;
        }

        return (bool) $mapped['retryable'] && ! (bool) $mapped['requires_reconcile'];
    }

    /**
     * @param  list<string>  $keys
     */
    private function firstString(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = data_get($payload, $key);

            if ($value === null || $value === '') {
                continue;
            }

            if (is_scalar($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function httpStatus(array $payload): ?int
    {
        $status = data_get($payload, 'http_status')
            ?? data_get($payload, 'status_code')
            ?? data_get($payload, 'error.status')
            ?? data_get($payload, 'data.error.status');

        if (is_numeric($status) && (int) $status >= 100) {
            return (int) $status;
        }

        $code = $this->firstString($payload, ['error.code', 'data.error.code', 'error_code', 'data.error_code']);
        if ($code !== null && preg_match('/^http_(\d{3})$/', strtolower($code), $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    private function category(?string $code, ?string $message, mixed $status, ?int $httpStatus): string
    {
        $haystack = strtolower(trim(implode(' ', array_filter([
            $code,
            $message,
            is_scalar($status) ? (string) $status : null,
        ]))));

        if ($httpStatus === 502 || str_contains($haystack, 'http_502') || str_contains($haystack, 'bad gateway')) {
            return self::CATEGORY_ARCA_INFRASTRUCTURE;
        }

        if (in_array($httpStatus, [408, 504], true)
            || str_contains($haystack, 'http_504')
            || str_contains($haystack, 'timeout')
            || str_contains($haystack, 'timed out')) {
            return self::CATEGORY_TIMEOUT;
        }

        if (in_array($httpStatus, [401, 403], true)
            || str_contains($haystack, 'token')
            || str_contains($haystack, 'certificado')
            || str_contains($haystack, 'certificate')
            || str_contains($haystack, 'clave privada')
            || str_contains($haystack, 'private key')
            || str_contains($haystack, 'wsaa')
            || str_contains($haystack, 'autentic')
            || str_contains($haystack, 'unauthor')
            || str_contains($haystack, 'forbidden')) {
            return self::CATEGORY_AUTHENTICATION;
        }

        if (str_contains($haystack, 'duplic')
            || str_contains($haystack, 'already exists')
            || str_contains($haystack, 'ya existe')
            || str_contains($haystack, 'idempot')) {
            return self::CATEGORY_DUPLICATED;
        }

        if (str_contains($haystack, 'numer')
            || str_contains($haystack, 'numbering')
            || str_contains($haystack, 'cbte_nro')
            || str_contains($haystack, 'punto de venta')) {
            return self::CATEGORY_NUMBERING;
        }

        if (in_array($httpStatus, [400, 409, 422], true)
            || str_contains($haystack, 'valid')
            || str_contains($haystack, 'rechaz')
            || str_contains($haystack, 'rejected')
            || str_contains($haystack, 'invalid')
            || str_contains($haystack, 'importe')
            || str_contains($haystack, 'iva')
            || str_contains($haystack, 'receptor')
            || str_contains($haystack, 'comprobante')) {
            return self::CATEGORY_VALIDATION;
        }

        if ($status === SaleFiscalDocument::STATUS_REJECTED) {
            return self::CATEGORY_VALIDATION;
        }

        return self::CATEGORY_UNKNOWN;
    }

    private function defaultRetryable(string $category, mixed $status): bool
    {
        return $category === self::CATEGORY_VALIDATION
            || $status === SaleFiscalDocument::STATUS_REJECTED;
    }

    private function defaultRequiresReconcile(string $category, mixed $status): bool
    {
        return in_array($category, [
            self::CATEGORY_ARCA_INFRASTRUCTURE,
            self::CATEGORY_TIMEOUT,
            self::CATEGORY_NUMBERING,
            self::CATEGORY_DUPLICATED,
        ], true)
            || in_array($status, [
                SaleFiscalDocument::STATUS_UNCERTAIN,
                SaleFiscalDocument::STATUS_PROCESSING,
            ], true);
    }

    private function userMessage(string $category, string $technicalMessage): string
    {
        $message = match ($category) {
            self::CATEGORY_ARCA_INFRASTRUCTURE => 'ARCA tuvo un error interno o de infraestructura. No se debe volver a emitir directamente. Usa Conciliar para verificar si el comprobante fue procesado.',
            self::CATEGORY_TIMEOUT => 'ARCA no respondio a tiempo. El estado del comprobante quedo incierto. Usa Conciliar antes de reintentar.',
            self::CATEGORY_AUTHENTICATION => 'La autenticacion fiscal fallo. Revisa certificado, clave privada, CUIT y servicio habilitado en ARCA.',
            self::CATEGORY_VALIDATION => 'ARCA rechazo los datos del comprobante. Revisa importes, IVA, documento del receptor, tipo de comprobante y punto de venta.',
            self::CATEGORY_NUMBERING => 'ARCA informo un problema de numeracion o punto de venta. Usa Conciliar antes de emitir nuevamente.',
            self::CATEGORY_DUPLICATED => 'La API fiscal informo un comprobante duplicado o ya existente. Usa Conciliar para recuperar el estado real.',
            default => 'No se pudo determinar el resultado fiscal. Revisa el detalle tecnico y concilia si el estado no es claro.',
        };

        if ($technicalMessage === '' || in_array($category, [
            self::CATEGORY_ARCA_INFRASTRUCTURE,
            self::CATEGORY_TIMEOUT,
            self::CATEGORY_AUTHENTICATION,
        ], true)) {
            return $message;
        }

        return $message.' Detalle: '.$technicalMessage;
    }

    private function action(string $category, bool $requiresReconcile, bool $retryable): string
    {
        if ($requiresReconcile) {
            return 'conciliar';
        }

        if ($category === self::CATEGORY_AUTHENTICATION) {
            return 'revisar_configuracion';
        }

        if ($category === self::CATEGORY_VALIDATION) {
            return 'revisar_datos';
        }

        return $retryable ? 'reintentar' : 'revisar';
    }

    private function boolValue(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }
}
