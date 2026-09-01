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
use App\Services\SaleStockReservationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MercadoPagoPointProvider implements PaymentProviderInterface
{
    public function __construct(
        private readonly MercadoPagoOrdersClient $client,
        private readonly PaymentService $paymentService,
        private readonly MercadoPagoSettingsResolver $settingsResolver,
        private readonly SaleStockReservationService $stockReservationService,
    ) {}

    public function createOrder(Business $business, Sale $sale, User $user, string $method): Payment
    {
        $settings = $this->settingsResolver->forSale($business, $sale->branch);
        $this->assertConfigured($settings);
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

            if ($lockedSale->point_status !== null) {
                $lockedSale->forceFill([
                    'point_status' => Sale::POINT_STATUS_PENDING,
                    'point_status_reason' => null,
                    'point_status_changed_at' => now(),
                ])->save();
                $this->stockReservationService->reserve($lockedSale);
            }

            $amount = $this->remainingAmount($lockedSale);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'payment' => 'La venta no tiene saldo pendiente para enviar a Mercado Pago Point.',
                ]);
            }

            if (! in_array($method, [
                Payment::METHOD_DEBIT_CARD,
                Payment::METHOD_CREDIT_CARD,
                Payment::METHOD_QR,
            ], true)) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Point integrado solo admite debito, credito o QR.',
                ]);
            }

            $payment = Payment::query()->create([
                'business_id' => $business->id,
                'sale_id' => $lockedSale->id,
                'created_by' => $user->id,
                'payment_destination_id' => $lockedSale->payment_destination_id,
                'method' => $method,
                'provider' => PaymentProvider::MercadoPago->value,
                'status' => PaymentStatus::Pending->value,
                'amount' => $amount,
                'currency' => 'ARS',
                'requested_at' => now(),
                'metadata' => [
                    'source' => 'mercadopago_point_order',
                    'requested_method' => $method,
                ],
            ]);

            $payment->forceFill([
                'external_reference' => $this->externalReference($business, $lockedSale, $payment),
                'idempotency_key' => $this->idempotencyKey($business, $lockedSale, $payment),
            ])->save();

            return $payment;
        });

        if ($payment->provider_order_id !== null) {
            return $payment->refresh();
        }

        try {
            $response = $this->client->createOrder(
                $this->createOrderPayload($sale, $payment, $settings),
                (string) $payment->idempotency_key,
                (string) $settings['access_token']
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
                'terminal_id' => $settings['point_terminal_id'],
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

        $payment->loadMissing('sale.business', 'sale.branch');
        $business = $payment->sale?->business;
        $settings = $business instanceof Business
            ? $this->settingsResolver->forSale($business, $payment->sale?->branch)
            : $this->settingsResolver->forBusiness(Business::query()->findOrFail($payment->business_id));

        $payload = $providerPayload ?? $this->client->getOrder(
            (string) $payment->provider_order_id,
            (string) $settings['access_token']
        );
        $providerStatus = (string) data_get($payload, 'status', data_get($payload, 'data.status', ''));
        $internalStatus = $this->internalStatus($providerStatus);
        $providerPayment = collect((array) data_get($payload, 'transactions.payments', data_get($payload, 'data.transactions.payments', [])))->first();

        $providerPaymentId = is_array($providerPayment)
            ? data_get($providerPayment, 'id')
            : null;

        return DB::transaction(function () use ($payment, $payload, $providerStatus, $internalStatus, $providerPaymentId): Payment {
            $lockedPayment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedSale = Sale::query()
                ->whereKey($lockedPayment->sale_id)
                ->lockForUpdate()
                ->firstOrFail();

            $metadata = [
                ...((array) $lockedPayment->metadata),
                'last_order_payload' => $payload,
            ];

            if ($this->requiresReconciliation($lockedSale, $lockedPayment, $internalStatus)) {
                $reason = $this->lateApprovalReason((string) $lockedSale->point_status);

                $metadata['reconciliation'] = [
                    'status' => Sale::POINT_RECONCILIATION_REQUIRED,
                    'reason' => $reason,
                    'detected_at' => now()->toIso8601String(),
                    'local_payment_status' => $lockedPayment->status,
                    'local_point_status' => $lockedSale->point_status,
                    'remote_status' => $providerStatus,
                    'provider_order_id' => $lockedPayment->provider_order_id,
                    'provider_payment_id' => $providerPaymentId ?? $lockedPayment->provider_payment_id,
                    'remote_payload' => $payload,
                ];

                $lockedPayment->forceFill([
                    'status' => PaymentStatus::Approved->value,
                    'provider_status' => $providerStatus,
                    'provider_payment_id' => $providerPaymentId ?? $lockedPayment->provider_payment_id,
                    'approved_at' => $lockedPayment->approved_at ?? now(),
                    'metadata' => $metadata,
                ])->save();

                $lockedSale->forceFill([
                    'point_status' => Sale::POINT_STATUS_RECONCILIATION_REQUIRED,
                    'point_status_reason' => $reason,
                    'point_status_changed_at' => now(),
                ])->save();

                $this->paymentService->syncSalePaymentSummary($lockedSale);

                return $lockedPayment->refresh();
            }

            if ($lockedPayment->status !== PaymentStatus::Pending->value) {
                $lockedPayment->forceFill([
                    'provider_status' => $providerStatus,
                    'provider_payment_id' => $providerPaymentId ?? $lockedPayment->provider_payment_id,
                    'metadata' => $metadata,
                ])->save();

                return $lockedPayment;
            }

            $updates = [
                'status' => $internalStatus,
                'provider_status' => $providerStatus,
                'provider_payment_id' => $providerPaymentId ?? $lockedPayment->provider_payment_id,
                'metadata' => $metadata,
            ];

            match ($internalStatus) {
                PaymentStatus::Approved->value => $updates['approved_at'] = $lockedPayment->approved_at ?? now(),
                PaymentStatus::Rejected->value => $updates['rejected_at'] = $lockedPayment->rejected_at ?? now(),
                PaymentStatus::Cancelled->value => $updates['cancelled_at'] = $lockedPayment->cancelled_at ?? now(),
                PaymentStatus::Refunded->value => $updates['refunded_at'] = $lockedPayment->refunded_at ?? now(),
                default => null,
            };

            $lockedPayment->forceFill($updates)->save();
            $this->paymentService->syncSalePaymentSummary($lockedSale);

            return $lockedPayment->refresh();
        });
    }

    public function cancelOrder(Payment $payment): void
    {
        if ($payment->provider !== PaymentProvider::MercadoPago->value || ! filled($payment->provider_order_id)) {
            return;
        }

        $payment->loadMissing('sale.business', 'sale.branch');
        $business = $payment->sale?->business;

        if (! $business instanceof Business) {
            return;
        }

        $settings = $this->settingsResolver->forSale($business, $payment->sale?->branch);
        $idempotencyKey = 'mp-point-cancel-p'.$payment->id;

        try {
            $response = $this->client->cancelOrder(
                (string) $payment->provider_order_id,
                $idempotencyKey,
                (string) $settings['access_token']
            );
        } catch (MercadoPagoApiException $exception) {
            $payment->forceFill([
                'metadata' => [
                    ...((array) $payment->metadata),
                    'provider_cancel_error' => $exception->getMessage(),
                    'provider_cancel_attempted_at' => now()->toIso8601String(),
                ],
            ])->save();

            throw $exception;
        }

        $payment->forceFill([
            'metadata' => [
                ...((array) $payment->metadata),
                'provider_cancel_response' => $response,
                'provider_cancelled_at' => now()->toIso8601String(),
            ],
        ])->save();
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
    private function createOrderPayload(Sale $sale, Payment $payment, array $settings): array
    {
        $payload = [
            'type' => 'point',
            'external_reference' => $payment->external_reference,
            'expiration_time' => (string) ($settings['point_expiration_time'] ?? 'PT15M'),
            'transactions' => [
                'payments' => [
                    [
                        'amount' => number_format((float) $payment->amount, 2, '.', ''),
                    ],
                ],
            ],
            'config' => [
                'point' => [
                    'terminal_id' => (string) $settings['point_terminal_id'],
                    'print_on_terminal' => (string) ($settings['point_print_on_terminal'] ?? 'no_ticket'),
                    'ticket_number' => $sale->sale_number,
                ],
                'payment_method' => [
                    'default_type' => $this->defaultTypeForMethod((string) $payment->method),
                ],
            ],
            'description' => 'Venta '.$sale->sale_number,
        ];

        $integrationData = $this->integrationData($settings);

        if ($integrationData !== []) {
            $payload['integration_data'] = $integrationData;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function integrationData(array $settings): array
    {
        $data = array_filter([
            'platform_id' => $settings['platform_id'] ?? null,
            'integrator_id' => $settings['integrator_id'] ?? null,
        ], fn (mixed $value): bool => filled($value));

        $sponsorId = $settings['sponsor_id'] ?? null;

        if (filled($sponsorId)) {
            $data['sponsor'] = ['id' => (string) $sponsorId];
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function assertConfigured(array $settings): void
    {
        $accessToken = trim((string) ($settings['access_token'] ?? ''));
        $terminalId = trim((string) ($settings['point_terminal_id'] ?? ''));

        if ($accessToken === '') {
            throw new MercadoPagoApiException('El access token de Mercado Pago no esta configurado.');
        }

        if ($terminalId === '') {
            throw new MercadoPagoApiException('La terminal Point de Mercado Pago no esta configurada.');
        }
    }

    private function externalReference(Business $business, Sale $sale, Payment $payment): string
    {
        return Str::limit("b{$business->id}-s{$sale->id}-p{$payment->id}", 64, '');
    }

    private function idempotencyKey(Business $business, Sale $sale, Payment $payment): string
    {
        return "mp-point-b{$business->id}-s{$sale->id}-p{$payment->id}";
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

    private function requiresReconciliation(Sale $sale, Payment $payment, string $remoteStatus): bool
    {
        return $remoteStatus === PaymentStatus::Approved->value
            && $payment->status !== PaymentStatus::Approved->value
            && in_array($sale->point_status, [
                Sale::POINT_STATUS_CANCELLED,
                Sale::POINT_STATUS_EXPIRED,
                Sale::POINT_STATUS_REJECTED,
            ], true);
    }

    private function lateApprovalReason(string $pointStatus): string
    {
        return match ($pointStatus) {
            Sale::POINT_STATUS_EXPIRED => 'remote_approved_after_expiration',
            Sale::POINT_STATUS_REJECTED => 'remote_approved_after_rejection',
            default => 'remote_approved_after_local_cancellation',
        };
    }

    private function defaultTypeForMethod(string $method): string
    {
        return match ($method) {
            Payment::METHOD_CREDIT_CARD => 'credit_card',
            Payment::METHOD_QR => 'qr',
            default => 'debit_card',
        };
    }
}
