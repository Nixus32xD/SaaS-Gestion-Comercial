<?php

namespace App\Services\Payments\MercadoPago;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class MercadoPagoOrdersClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createOrder(array $payload, string $idempotencyKey, ?string $accessToken = null): array
    {
        return $this->post('/v1/orders', $payload, $idempotencyKey, 201, $accessToken);
    }

    /**
     * @return array<string, mixed>
     */
    public function getOrder(string $orderId, ?string $accessToken = null): array
    {
        try {
            $response = $this->request($accessToken)->get('/v1/orders/'.$this->pathSegment($orderId));
        } catch (ConnectionException $exception) {
            throw new MercadoPagoApiTimeoutException($this->connectionUnavailableMessage(), previous: $exception);
        }

        return $this->normalizeResponse($response->status(), $response->json(), $response->body());
    }

    /**
     * @return array<string, mixed>
     */
    public function simulateOrder(string $orderId, string $status, ?string $accessToken = null): array
    {
        return $this->post('/v1/orders/'.$this->pathSegment($orderId).'/events', [
            'status' => $status,
        ], 'simulate-'.$orderId.'-'.$status, 200, $accessToken);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(
        string $uri,
        array $payload,
        string $idempotencyKey,
        int $expectedStatus,
        ?string $accessToken = null
    ): array {
        try {
            $response = $this->request($accessToken)
                ->withHeaders(['X-Idempotency-Key' => $idempotencyKey])
                ->post($uri, $payload);
        } catch (ConnectionException $exception) {
            throw new MercadoPagoApiTimeoutException($this->connectionUnavailableMessage(), previous: $exception);
        }

        $data = $this->normalizeResponse($response->status(), $response->json(), $response->body());

        if ($response->status() !== $expectedStatus && ! ($response->status() >= 200 && $response->status() < 300)) {
            throw new MercadoPagoApiException($this->errorMessage($data));
        }

        return $data;
    }

    private function request(?string $accessToken = null): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->asJson()
            ->connectTimeout($this->connectTimeout())
            ->timeout($this->timeout())
            ->withToken($this->token($accessToken));
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

        throw new MercadoPagoApiException($this->errorMessage([
            ...$payload,
            'http_status' => $statusCode,
            'body' => $body,
        ]));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function errorMessage(array $payload): string
    {
        return (string) (
            data_get($payload, 'message')
            ?? data_get($payload, 'error.message')
            ?? data_get($payload, 'error')
            ?? data_get($payload, 'body')
            ?? 'Mercado Pago rechazo la operacion.'
        );
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.mercadopago.base_url', 'https://api.mercadopago.com'), '/');
    }

    private function token(?string $accessToken = null): string
    {
        $token = trim((string) ($accessToken ?: config('services.mercadopago.access_token')));

        if ($token === '') {
            throw new MercadoPagoApiException('El access token de Mercado Pago no esta configurado.');
        }

        return $token;
    }

    private function timeout(): int
    {
        return max(1, (int) config('services.mercadopago.timeout', 30));
    }

    private function connectTimeout(): int
    {
        return max(1, (int) config('services.mercadopago.connect_timeout', 5));
    }

    private function pathSegment(string $value): string
    {
        return rawurlencode($value);
    }

    private function connectionUnavailableMessage(): string
    {
        return 'Mercado Pago no esta disponible actualmente. Intenta nuevamente.';
    }
}
