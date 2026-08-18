<?php

namespace App\Jobs\Payments;

use App\Enums\Payments\PaymentProvider;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Services\Payments\MercadoPago\MercadoPagoPaymentCompletionService;
use App\Services\Payments\MercadoPago\MercadoPagoPointProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ProcessMercadoPagoOrderWebhookJob implements ShouldQueue
{
    use Queueable;

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

        $event = $this->event($payment, $orderId);

        if ($event->processed_at !== null) {
            return;
        }

        $payment = $provider->syncPayment($payment, $this->hasOrderStatus() ? $this->orderPayload() : null);
        $completionService->complete($payment);

        $event->forceFill([
            'business_id' => $payment->business_id,
            'payment_id' => $payment->id,
            'processed_at' => now(),
        ])->save();
    }

    private function event(Payment $payment, string $orderId): PaymentEvent
    {
        return DB::transaction(function () use ($payment, $orderId): PaymentEvent {
            return PaymentEvent::query()->firstOrCreate(
                [
                    'provider' => PaymentProvider::MercadoPago->value,
                    'event_key' => $this->eventKey($orderId),
                ],
                [
                    'business_id' => $payment->business_id,
                    'payment_id' => $payment->id,
                    'event_type' => (string) data_get($this->payload, 'action'),
                    'resource_id' => $orderId,
                    'payload' => $this->payload,
                ]
            );
        });
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
}
