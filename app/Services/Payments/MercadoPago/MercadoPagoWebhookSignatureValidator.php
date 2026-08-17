<?php

namespace App\Services\Payments\MercadoPago;

use App\Models\BusinessMercadoPagoCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MercadoPagoWebhookSignatureValidator
{
    public function isValid(Request $request): bool
    {
        $secrets = $this->secretCandidates();

        if ($secrets->isEmpty()) {
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

        return $secrets->contains(function (string $secret) use ($manifest, $hash): bool {
            $expected = hash_hmac('sha256', $manifest, $secret);

            return hash_equals($expected, $hash);
        });
    }

    /**
     * @return Collection<int, string>
     */
    private function secretCandidates(): Collection
    {
        $globalSecret = trim((string) config('services.mercadopago.webhook_secret'));

        return collect([$globalSecret])
            ->merge(
                BusinessMercadoPagoCredential::query()
                    ->where('is_enabled', true)
                    ->whereNotNull('webhook_secret')
                    ->get(['webhook_secret'])
                    ->map(fn (BusinessMercadoPagoCredential $credential): string => trim((string) $credential->webhook_secret))
            )
            ->filter()
            ->unique()
            ->values();
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
