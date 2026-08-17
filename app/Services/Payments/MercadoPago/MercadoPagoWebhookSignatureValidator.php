<?php

namespace App\Services\Payments\MercadoPago;

use Illuminate\Http\Request;

class MercadoPagoWebhookSignatureValidator
{
    public function isValid(Request $request): bool
    {
        $secret = trim((string) config('services.mercadopago.webhook_secret'));

        if ($secret === '') {
            return app()->environment('testing');
        }

        $signature = $this->signatureParts((string) $request->header('x-signature', ''));
        $timestamp = $signature['ts'] ?? null;
        $hash = $signature['v1'] ?? null;

        if ($timestamp === null || $hash === null) {
            return false;
        }

        $manifest = $this->manifest(
            (string) ($request->query('data.id') ?: data_get($request->json()->all(), 'data.id', '')),
            (string) $request->header('x-request-id', ''),
            $timestamp
        );

        $expected = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($expected, $hash);
    }

    /**
     * @return array<string, string>
     */
    private function signatureParts(string $header): array
    {
        $parts = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if ($key !== null && $value !== null) {
                $parts[trim($key)] = trim($value);
            }
        }

        return $parts;
    }

    private function manifest(string $dataId, string $requestId, string $timestamp): string
    {
        $manifest = '';

        if ($dataId !== '') {
            $manifest .= 'id:'.strtolower($dataId).';';
        }

        if ($requestId !== '') {
            $manifest .= 'request-id:'.$requestId.';';
        }

        return $manifest.'ts:'.$timestamp.';';
    }
}
