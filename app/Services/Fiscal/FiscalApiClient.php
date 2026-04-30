<?php

namespace App\Services\Fiscal;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class FiscalApiClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function upsertCompany(array $payload, ?string $company = null): array
    {
        $company = trim((string) $company);

        if ($company !== '') {
            return $this->put('/fiscal/companies/'.$this->pathSegment($company), $payload);
        }

        return $this->post('/fiscal/companies', $payload);
    }

    /**
     * @return array{status: array<string, mixed>, activities: array<string, mixed>, points_of_sale: array<string, mixed>}
     */
    public function companyOverview(string $company): array
    {
        $company = $this->pathSegment($company);

        return $this->pooledGet([
            'status' => "/fiscal/companies/{$company}/status",
            'activities' => "/fiscal/companies/{$company}/activities",
            'points_of_sale' => "/fiscal/companies/{$company}/points-of-sale",
        ], $this->defaultTimeout());
    }

    /**
     * @return array<string, mixed>
     */
    public function companyStatus(string $company): array
    {
        return $this->get('/fiscal/companies/'.$this->pathSegment($company).'/status');
    }

    /**
     * @return array<string, mixed>
     */
    public function companyActivities(string $company): array
    {
        return $this->get('/fiscal/companies/'.$this->pathSegment($company).'/activities');
    }

    /**
     * @return array<string, mixed>
     */
    public function companyPointsOfSale(string $company): array
    {
        return $this->get('/fiscal/companies/'.$this->pathSegment($company).'/points-of-sale');
    }

    /**
     * @return array<string, mixed>
     */
    public function requestCaea(string $company, string $period, int $order): array
    {
        return $this->post('/fiscal/companies/'.$this->pathSegment($company).'/caea/request', [
            'period' => $period,
            'order' => $order,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function consultCaea(string $company, string $period, int $order): array
    {
        return $this->get('/fiscal/companies/'.$this->pathSegment($company).'/caea/consult', [
            'period' => $period,
            'order' => $order,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function reportCaeaDocument(string|int $documentId): array
    {
        return $this->post('/fiscal/documents/'.$this->pathSegment($documentId).'/caea/report', []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function generateCredentialCsr(string $company, array $payload): array
    {
        return $this->post('/fiscal/companies/'.$this->pathSegment($company).'/credentials/csr', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function storeCredentialCertificate(string $company, string|int $credentialId, array $payload): array
    {
        return $this->put(
            '/fiscal/companies/'.$this->pathSegment($company).'/credentials/'.$this->pathSegment($credentialId).'/certificate',
            $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createDocument(array $payload): array
    {
        return $this->post('/fiscal/documents', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function document(string|int $documentId): array
    {
        return $this->get('/fiscal/documents/'.$this->pathSegment($documentId));
    }

    /**
     * @return array<string, mixed>
     */
    public function documentByOrigin(string $businessId, string $originType, string|int $originId): array
    {
        return $this->get('/fiscal/documents/by-origin', [
            'business_id' => $businessId,
            'origin_type' => $originType,
            'origin_id' => $originId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function retryDocument(string|int $documentId): array
    {
        return $this->post('/fiscal/documents/'.$this->pathSegment($documentId).'/retry', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function reconcileDocument(string|int $documentId): array
    {
        return $this->post('/fiscal/documents/'.$this->pathSegment($documentId).'/reconcile', []);
    }

    /**
     * @param  array<string, string>  $uris
     * @return array<string, array<string, mixed>>
     */
    private function pooledGet(array $uris, int $timeout): array
    {
        $baseUrl = $this->baseUrl();
        $token = $this->token();

        $responses = Http::pool(function (Pool $pool) use ($uris, $baseUrl, $token, $timeout): void {
            foreach ($uris as $key => $uri) {
                $pool->as($key)
                    ->baseUrl($baseUrl)
                    ->acceptJson()
                    ->asJson()
                    ->connectTimeout($this->connectTimeout())
                    ->timeout($timeout)
                    ->withHeaders(['X-Trace-Id' => $this->traceId()])
                    ->withToken($token)
                    ->get($uri);
            }
        }, count($uris));

        return collect($responses)
            ->map(fn (Response|Throwable $response): array => $this->normalizePooledResponse($response))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $uri, array $query = [], ?int $timeout = null): array
    {
        try {
            $response = $this->request($timeout)->get($uri, $query);
        } catch (ConnectionException $exception) {
            throw new FiscalApiTimeoutException($this->connectionUnavailableMessage(), previous: $exception);
        }

        return $this->normalizeResponse($response->status(), $response->json(), $response->body());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $uri, array $payload, ?int $timeout = null): array
    {
        try {
            $response = $this->request($timeout)->post($uri, $payload);
        } catch (ConnectionException $exception) {
            throw new FiscalApiTimeoutException($this->connectionUnavailableMessage(), previous: $exception);
        }

        return $this->normalizeResponse($response->status(), $response->json(), $response->body());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function put(string $uri, array $payload, ?int $timeout = null): array
    {
        try {
            $response = $this->request($timeout)->put($uri, $payload);
        } catch (ConnectionException $exception) {
            throw new FiscalApiTimeoutException($this->connectionUnavailableMessage(), previous: $exception);
        }

        return $this->normalizeResponse($response->status(), $response->json(), $response->body());
    }

    private function request(?int $timeout = null): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->asJson()
            ->connectTimeout($this->connectTimeout())
            ->timeout($timeout ?? $this->defaultTimeout())
            ->withHeaders(['X-Trace-Id' => $this->traceId()])
            ->withToken($this->token());
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>
     */
    private function normalizeResponse(int $statusCode, ?array $json, string $body): array
    {
        $payload = $json ?? [];

        if ($statusCode >= 200 && $statusCode < 300) {
            return $payload;
        }

        return [
            ...$payload,
            'status' => $payload['status'] ?? 'error',
            'http_status' => $statusCode,
            'error' => [
                'code' => data_get($payload, 'error.code', $payload['error_code'] ?? "http_{$statusCode}"),
                'message' => data_get($payload, 'error.message', $payload['message'] ?? $body),
                'technical_message' => data_get(
                    $payload,
                    'error.technical_message',
                    $payload['technical_message'] ?? ($payload['message'] ?? $body)
                ),
            ],
        ];
    }

    private function pathSegment(string|int $value): string
    {
        return rawurlencode((string) $value);
    }

    private function baseUrl(): string
    {
        $baseUrl = rtrim((string) config('fiscal.base_url'), '/');

        if ($baseUrl === '') {
            throw new FiscalApiException('La URL base de la API fiscal no esta configurada.');
        }

        return $baseUrl;
    }

    private function token(): string
    {
        $token = trim((string) config('fiscal.token'));

        if ($token === '') {
            throw new FiscalApiException('El token de la API fiscal no esta configurado.');
        }

        return $token;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizePooledResponse(Response|Throwable $response): array
    {
        if ($response instanceof ConnectionException) {
            throw new FiscalApiTimeoutException($this->connectionUnavailableMessage(), previous: $response);
        }

        if ($response instanceof Throwable) {
            throw new FiscalApiException($response->getMessage(), previous: $response);
        }

        return $this->normalizeResponse($response->status(), $response->json(), $response->body());
    }

    private function defaultTimeout(): int
    {
        return max(1, (int) config('fiscal.timeout', 60));
    }

    private function connectTimeout(): int
    {
        return max(1, (int) config('fiscal.connect_timeout', 3));
    }

    private function connectionUnavailableMessage(): string
    {
        return 'La API fiscal no esta disponible actualmente. Revisa que el servicio este iniciado e intenta nuevamente.';
    }

    private function traceId(): string
    {
        return (string) Str::uuid();
    }
}
