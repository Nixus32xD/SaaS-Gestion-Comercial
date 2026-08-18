<?php

namespace App\Services\Payments\MercadoPago;

use App\Enums\Payments\PaymentProvider;
use App\Models\BusinessMercadoPagoCredential;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MercadoPagoWebhookSignatureValidator
{
    public function isValid(Request $request): bool
    {
        return $this->validatedPayment($request) !== null;
    }

    public function validatedPayment(Request $request): ?Payment
    {
        $signature = $this->signatureParts((string) $request->header('x-signature', ''));
        $timestamp = $signature['ts'] ?? null;
        $hash = $signature['v1'] ?? null;

        if ($timestamp === null || $hash === null) {
            return null;
        }

        $payload = $request->json()->all();
        $manifest = $this->manifest(
            $this->signatureDataId($request, $payload),
            (string) $request->header('x-request-id', ''),
            $timestamp
        );

        return $this->paymentCandidates($request, $payload)
            ->first(fn (Payment $payment): bool => $this->signatureMatches($payment, $manifest, $hash));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return Collection<int, Payment>
     */
    private function paymentCandidates(Request $request, array $payload): Collection
    {
        $orderId = $this->orderId($request, $payload);
        $externalReference = $this->externalReference($payload);

        if ($orderId === '' && $externalReference === '') {
            return collect();
        }

        return Payment::query()
            ->where('provider', PaymentProvider::MercadoPago->value)
            ->where(function (Builder $query) use ($orderId, $externalReference): void {
                if ($orderId !== '' && $externalReference !== '') {
                    $query
                        ->where('provider_order_id', $orderId)
                        ->where('external_reference', $externalReference);

                    return;
                }

                if ($orderId !== '') {
                    $query->where('provider_order_id', $orderId);
                }

                if ($externalReference !== '') {
                    $query->where('external_reference', $externalReference);
                }
            })
            ->get();
    }

    private function signatureMatches(Payment $payment, string $manifest, string $hash): bool
    {
        $secret = $this->webhookSecretForPayment($payment);

        if ($secret === null) {
            return false;
        }

        $expected = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($expected, $hash);
    }

    private function webhookSecretForPayment(Payment $payment): ?string
    {
        $credential = BusinessMercadoPagoCredential::query()
            ->where('business_id', (int) $payment->business_id)
            ->where('is_enabled', true)
            ->first(['webhook_secret']);

        $secret = trim((string) $credential?->webhook_secret);

        return $secret !== '' ? $secret : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function orderId(Request $request, array $payload): string
    {
        return trim((string) (
            $this->signatureDataId($request, $payload)
            ?: data_get($payload, 'id', '')
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function signatureDataId(Request $request, array $payload): string
    {
        return trim((string) (
            $request->query('data.id')
            ?: data_get($payload, 'data.id', '')
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function externalReference(array $payload): string
    {
        return trim((string) (
            data_get($payload, 'data.external_reference')
            ?? data_get($payload, 'external_reference')
            ?? ''
        ));
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
