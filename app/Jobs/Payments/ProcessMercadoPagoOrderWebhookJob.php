<?php

namespace App\Jobs\Payments;

use App\Enums\Payments\PaymentProvider;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Services\Payments\MercadoPago\MercadoPagoPaymentCompletionService;
use App\Services\Payments\MercadoPago\MercadoPagoPointProvider;
use DateTime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ProcessMercadoPagoOrderWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $maxExceptions = 5;

    public int $timeout = 120;

    /**
     * @var array<string, mixed>
     */
    private array $payload;

    private ?string $requestId;

    private ?int $paymentId = null;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        array $payload,
        ?string $requestId = null,
        ?int $paymentId = null,
    ) {
        $this->payload = $payload;
        $this->requestId = $requestId;
        $this->paymentId = $paymentId;
    }

    public function handle(
        MercadoPagoPointProvider $provider,
        MercadoPagoPaymentCompletionService $completionService,
    ): void {
        $orderId = $this->orderId();

        if ($orderId === '') {
            return;
        }

        $payment = $this->payment();

        if ($payment === null) {
            return;
        }

        $event = $this->claimEvent($payment, $orderId);

        if ($event === null) {
            return;
        }

        try {
            $payment = $provider->syncPayment($payment, $this->hasOrderStatus() ? $this->orderPayload() : null);
            $completionService->complete($payment);
        } catch (Throwable $exception) {
            $this->releaseEvent($event, $exception);

            Log::warning('mercadopago_webhook_processing_failed', [
                ...$this->context($payment, $orderId),
                'payment_event_id' => $event->id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $event->forceFill([
            'business_id' => $payment->business_id,
            'payment_id' => $payment->id,
            'processing_at' => null,
            'processed_at' => now(),
            'last_error' => null,
        ])->save();
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function retryUntil(): DateTime
    {
        return now()->addHours(6);
    }

    public function failed(Throwable $exception): void
    {
        $payment = $this->payment();
        $orderId = $this->orderId();

        Log::error('critical_job_failed', [
            ...$this->context($payment, $orderId),
            'error' => $exception->getMessage(),
        ]);
    }

    private function claimEvent(Payment $payment, string $orderId): ?PaymentEvent
    {
        return DB::transaction(function () use ($payment, $orderId): ?PaymentEvent {
            $event = PaymentEvent::query()
                ->where('provider', PaymentProvider::MercadoPago->value)
                ->where('event_key', $this->eventKey($orderId))
                ->lockForUpdate()
                ->first();

            if ($event === null) {
                $event = PaymentEvent::query()->create([
                    'business_id' => $payment->business_id,
                    'payment_id' => $payment->id,
                    'provider' => PaymentProvider::MercadoPago->value,
                    'event_key' => $this->eventKey($orderId),
                    'event_type' => (string) data_get($this->payload, 'action'),
                    'resource_id' => $orderId,
                    'payload' => $this->payload,
                ]);
            }

            if ($event->processed_at !== null) {
                return null;
            }

            if ($this->hasActiveClaim($event)) {
                throw new RuntimeException('El evento de Mercado Pago ya esta siendo procesado por otro worker.');
            }

            $event->forceFill([
                'business_id' => $payment->business_id,
                'payment_id' => $payment->id,
                'processing_at' => now(),
                'last_error' => null,
            ])->save();

            return $event;
        });
    }

    private function hasActiveClaim(PaymentEvent $event): bool
    {
        return $event->processing_at !== null
            && $event->processing_at->greaterThan(now()->subMinutes(5));
    }

    private function releaseEvent(PaymentEvent $event, Throwable $exception): void
    {
        PaymentEvent::query()
            ->whereKey($event->id)
            ->whereNull('processed_at')
            ->update([
                'processing_at' => null,
                'last_error' => $exception->getMessage(),
                'updated_at' => now(),
            ]);
    }

    private function payment(): ?Payment
    {
        if ($this->paymentId === null) {
            return null;
        }

        return Payment::query()
            ->whereKey($this->paymentId)
            ->where('provider', PaymentProvider::MercadoPago->value)
            ->first();
    }

    private function eventKey(string $orderId): string
    {
        $eventId = (string) data_get($this->payload, 'id');

        if ($eventId !== '') {
            return $eventId;
        }

        return implode(':', array_filter([
            (string) data_get($this->payload, 'action', 'order'),
            $orderId,
            $this->requestId,
            (string) data_get($this->payload, 'date_created'),
        ]));
    }

    private function orderId(): string
    {
        return (string) (
            data_get($this->payload, 'data.id')
            ?? data_get($this->payload, 'id')
            ?? ''
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPayload(): array
    {
        $data = (array) data_get($this->payload, 'data', []);

        return $data !== [] ? $data : $this->payload;
    }

    private function hasOrderStatus(): bool
    {
        return data_get($this->payload, 'data.status') !== null
            || data_get($this->payload, 'status') !== null;
    }

    /**
     * @return array<string, int|string|null>
     */
    private function context(?Payment $payment, string $orderId): array
    {
        $payment?->loadMissing('sale:id,branch_id');

        return [
            'job' => self::class,
            'business_id' => $payment?->business_id,
            'branch_id' => $payment?->sale?->branch_id,
            'payment_id' => $payment?->id,
            'order_id' => $orderId !== '' ? $orderId : $payment?->provider_order_id,
            'provider_payment_id' => $payment?->provider_payment_id,
        ];
    }
}
