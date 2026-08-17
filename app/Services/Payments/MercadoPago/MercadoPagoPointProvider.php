<?php

namespace App\Services\Payments\MercadoPago;

use App\Contracts\Payments\PaymentProviderInterface;
use App\Enums\Payments\PaymentProvider;
use App\Enums\Payments\PaymentStatus;
use App\Models\Business;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use App\Services\Payments\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MercadoPagoPointProvider implements PaymentProviderInterface
{
    public function __construct(
        private readonly MercadoPagoOrdersClient $client,
        private readonly PaymentService $paymentService,
    ) {}

    public function createOrder(Business $business, Sale $sale, User $user, string $method): Payment
    {
        $payment = DB::transaction(function () use ($business, $sale, $user, $method): Payment {
            $lockedSale = Sale::query()
                ->forBusiness($business->id)
                ->whereKey($sale->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = Payment::query()
                ->forBusiness($business->id)
                ->where('sale_id', $lockedSale->id)
                ->where('provider', PaymentProvider::MercadoPago->value)
                ->whereIn('status', [PaymentStatus::Pending->value])
                ->latest('id')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $amount = $this->remainingAmount($lockedSale);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'payment' => 'La venta no tiene saldo pendiente para enviar a Mercado Pago Point.',
                ]);
            }

            if (! in_array($method, [Payment::METHOD_DEBIT_CARD, Payment::METHOD_CREDIT_CARD], true)) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Point integrado solo admite debito o credito.',
                ]);
            }

            $payment = Payment::query()->create([
                'business_id' => $business->id,
                'sale_id' => $lockedSale->id,
                'created_by' => $user->id,
                'method' => $method,
                'provider' => PaymentProvider::MercadoPago->value,
                'status' => PaymentStatus::Pending->value,
                'amount' => $amount,
                'currency' => 'ARS',
                'idempotency_key' => 'mp-point-sale-'.$business->id.'-'.$lockedSale->id,
                'requested_at' => now(),
                'metadata' => [
                    'source' => 'mercadopago_point_order',
                ],
            ]);

            $payment->forceFill([
                'external_reference' => $this->externalReference($business, $lockedSale, $payment),
            ])->save();

            return $payment;
        });

        if ($payment->provider_order_id !== null) {
            return $payment->refresh();
        }

        try {
            $response = $this->client->createOrder(
                $this->createOrderPayload($sale, $payment),
                (string) $payment->idempotency_key
            );
        } catch (MercadoPagoApiException $exception) {
            $payment->forceFill([
                'provider_status' => 'api_error',
                'metadata' => [
                    ...((array) $payment->metadata),
                    'last_error' => $exception->getMessage(),
                ],
            ])->save();

            throw $exception;
        }

        $providerPayment = collect((array) data_get($response, 'transactions.payments', []))->first();

        $payment->forceFill([
            'provider_order_id' => (string) data_get($response, 'id'),
            'provider_payment_id' => is_array($providerPayment) ? data_get($providerPayment, 'id') : null,
            'provider_status' => (string) data_get($response, 'status', 'created'),
            'metadata' => [
                ...((array) $payment->metadata),
                'terminal_id' => $this->terminalId(),
                'order_response' => $response,
            ],
        ])->save();

        return $payment->refresh();
    }

    public function syncPayment(Payment $payment, ?array $providerPayload = null): Payment
    {
        if ($payment->provider !== PaymentProvider::MercadoPago->value) {
            throw ValidationException::withMessages([
                'payment' => 'El pago no pertenece a Mercado Pago.',
            ]);
        }

        $payload = $providerPayload ?? $this->client->getOrder((string) $payment->provider_order_id);
        $providerStatus = (string) data_get($payload, 'status', data_get($payload, 'data.status', ''));
        $internalStatus = $this->internalStatus($providerStatus);
        $providerPayment = collect((array) data_get($payload, 'transactions.payments', data_get($payload, 'data.transactions.payments', [])))->first();

        $updates = [
            'status' => $internalStatus,
            'provider_status' => $providerStatus,
            'provider_payment_id' => is_array($providerPayment)
                ? (data_get($providerPayment, 'id') ?? $payment->provider_payment_id)
                : $payment->provider_payment_id,
            'metadata' => [
                ...((array) $payment->metadata),
                'last_order_payload' => $payload,
            ],
        ];

        match ($internalStatus) {
            PaymentStatus::Approved->value => $updates['approved_at'] = $payment->approved_at ?? now(),
            PaymentStatus::Rejected->value => $updates['rejected_at'] = $payment->rejected_at ?? now(),
            PaymentStatus::Cancelled->value => $updates['cancelled_at'] = $payment->cancelled_at ?? now(),
            PaymentStatus::Refunded->value => $updates['refunded_at'] = $payment->refunded_at ?? now(),
            default => null,
        };

        $payment->forceFill($updates)->save();
        $this->paymentService->syncSalePaymentSummary($payment->sale);

        return $payment->refresh();
    }

    private function remainingAmount(Sale $sale): float
    {
        $approvedTotal = round((float) Payment::query()
            ->where('business_id', $sale->business_id)
            ->where('sale_id', $sale->id)
            ->where('status', PaymentStatus::Approved->value)
            ->sum('amount'), 2);

        return round(max((float) $sale->total - $approvedTotal, 0), 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function createOrderPayload(Sale $sale, Payment $payment): array
    {
        $payload = [
            'type' => 'point',
            'external_reference' => $payment->external_reference,
            'expiration_time' => (string) config('services.mercadopago.point_expiration_time', 'PT15M'),
            'transactions' => [
                'payments' => [
                    [
                        'amount' => number_format((float) $payment->amount, 2, '.', ''),
                    ],
                ],
            ],
            'config' => [
                'point' => [
                    'terminal_id' => $this->terminalId(),
                    'print_on_terminal' => (string) config('services.mercadopago.point_print_on_terminal', 'no_ticket'),
                    'ticket_number' => $sale->sale_number,
                ],
                'payment_method' => [
                    'default_type' => $payment->method === Payment::METHOD_CREDIT_CARD
                        ? 'credit_card'
                        : 'debit_card',
                ],
            ],
            'description' => 'Venta '.$sale->sale_number,
        ];

        $integrationData = $this->integrationData();

        if ($integrationData !== []) {
            $payload['integration_data'] = $integrationData;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function integrationData(): array
    {
        $data = array_filter([
            'platform_id' => config('services.mercadopago.platform_id'),
            'integrator_id' => config('services.mercadopago.integrator_id'),
        ], fn (mixed $value): bool => filled($value));

        $sponsorId = config('services.mercadopago.sponsor_id');

        if (filled($sponsorId)) {
            $data['sponsor'] = ['id' => (string) $sponsorId];
        }

        return $data;
    }

    private function terminalId(): string
    {
        $terminalId = trim((string) config('services.mercadopago.point_terminal_id'));

        if ($terminalId === '') {
            throw new MercadoPagoApiException('La terminal Point de Mercado Pago no esta configurada.');
        }

        return $terminalId;
    }

    private function externalReference(Business $business, Sale $sale, Payment $payment): string
    {
        return Str::limit("b{$business->id}-s{$sale->id}-p{$payment->id}", 64, '');
    }

    private function internalStatus(string $providerStatus): string
    {
        return match ($providerStatus) {
            'processed' => PaymentStatus::Approved->value,
            'failed' => PaymentStatus::Rejected->value,
            'canceled', 'expired' => PaymentStatus::Cancelled->value,
            'refunded' => PaymentStatus::Refunded->value,
            default => PaymentStatus::Pending->value,
        };
    }
}
