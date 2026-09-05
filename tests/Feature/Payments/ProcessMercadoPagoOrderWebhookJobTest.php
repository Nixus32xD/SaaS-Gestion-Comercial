<?php

use App\Enums\Payments\PaymentProvider;
use App\Enums\Payments\PaymentStatus;
use App\Jobs\Payments\ProcessMercadoPagoOrderWebhookJob;
use App\Models\Business;
use App\Models\BusinessMercadoPagoCredential;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Sale;
use App\Models\User;
use App\Services\Payments\MercadoPago\MercadoPagoApiTimeoutException;
use App\Services\Payments\MercadoPago\MercadoPagoPaymentCompletionService;
use App\Services\Payments\MercadoPago\MercadoPagoPointProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

test('Mercado Pago webhook job releases an unprocessed event after a temporary provider failure', function () {
    [$payment, $payload] = webhookJobPayment('ORD-RETRY-01', 'notification-retry-01');
    $job = new ProcessMercadoPagoOrderWebhookJob($payload, 'request-retry-01', $payment->id);

    Http::fake([
        'https://api.mercadopago.com/v1/orders/ORD-RETRY-01' => fn () => throw new ConnectionException('temporary network failure'),
    ]);

    expect(fn () => $job->handle(
        app(MercadoPagoPointProvider::class),
        app(MercadoPagoPaymentCompletionService::class),
    ))->toThrow(MercadoPagoApiTimeoutException::class);

    $event = PaymentEvent::query()->sole();

    expect($event->processed_at)->toBeNull()
        ->and($event->processing_at)->toBeNull()
        ->and($event->last_error)->toContain('Mercado Pago no esta disponible')
        ->and($job->tries)->toBe(5)
        ->and($job->maxExceptions)->toBe(5)
        ->and($job->backoff())->toBe([60, 300, 900, 1800]);
});

test('Mercado Pago webhook job does not process a completed event twice', function () {
    [$payment, $payload] = webhookJobPayment('ORD-IDEMPOTENT-01', 'notification-idempotent-01');
    $job = new ProcessMercadoPagoOrderWebhookJob($payload, 'request-idempotent-01', $payment->id);

    Http::fake([
        'https://api.mercadopago.com/v1/orders/ORD-IDEMPOTENT-01' => Http::response([
            'id' => 'ORD-IDEMPOTENT-01',
            'status' => 'processed',
            'transactions' => ['payments' => [['id' => 'PAY-IDEMPOTENT-01', 'status' => 'processed']]],
        ]),
    ]);

    $job->handle(app(MercadoPagoPointProvider::class), app(MercadoPagoPaymentCompletionService::class));
    $job->handle(app(MercadoPagoPointProvider::class), app(MercadoPagoPaymentCompletionService::class));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Approved->value)
        ->and(PaymentEvent::query()->count())->toBe(1)
        ->and(PaymentEvent::query()->sole()->processed_at)->not->toBeNull();

    Http::assertSentCount(1);
});

test('Mercado Pago webhook job defers a duplicate while another worker holds the event claim', function () {
    [$payment, $payload] = webhookJobPayment('ORD-CLAIMED-01', 'notification-claimed-01');
    PaymentEvent::query()->create([
        'business_id' => $payment->business_id,
        'payment_id' => $payment->id,
        'provider' => PaymentProvider::MercadoPago->value,
        'event_key' => 'notification-claimed-01',
        'event_type' => 'order.processed',
        'resource_id' => 'ORD-CLAIMED-01',
        'payload' => $payload,
        'processing_at' => now(),
    ]);
    $job = new ProcessMercadoPagoOrderWebhookJob($payload, 'request-claimed-01', $payment->id);

    Http::fake();

    expect(fn () => $job->handle(
        app(MercadoPagoPointProvider::class),
        app(MercadoPagoPaymentCompletionService::class),
    ))->toThrow(RuntimeException::class);

    expect(PaymentEvent::query()->sole()->processed_at)->toBeNull();
    Http::assertNothingSent();
});

test('Mercado Pago webhook job writes structured context when retries are exhausted', function () {
    [$payment, $payload] = webhookJobPayment('ORD-FAILED-01', 'notification-failed-01');
    $job = new ProcessMercadoPagoOrderWebhookJob($payload, 'request-failed-01', $payment->id);

    Log::spy();
    $job->failed(new RuntimeException('provider unavailable after retries'));

    Log::shouldHaveReceived('error')
        ->once()
        ->with('critical_job_failed', Mockery::on(function (array $context) use ($payment): bool {
            return $context['job'] === ProcessMercadoPagoOrderWebhookJob::class
                && $context['business_id'] === $payment->business_id
                && $context['payment_id'] === $payment->id
                && $context['order_id'] === 'ORD-FAILED-01'
                && $context['error'] === 'provider unavailable after retries';
        }));
});

/**
 * @return array{0: Payment, 1: array<string, mixed>}
 */
function webhookJobPayment(string $orderId, string $notificationId): array
{
    $business = Business::factory()->create();
    BusinessMercadoPagoCredential::query()->create([
        'business_id' => $business->id,
        'is_enabled' => true,
        'environment' => 'testing',
        'public_key' => 'APP_USR-public-test',
        'access_token' => 'APP_USR-testing-token',
        'webhook_secret' => 'testing-webhook-secret',
        'point_terminal_id' => 'NEWLAND_N950__SBX0000001',
        'point_expiration_time' => 'PT15M',
        'point_print_on_terminal' => 'no_ticket',
    ]);
    $user = User::factory()->businessAdmin($business->id)->create();
    $sale = Sale::query()->create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'sale_number' => 'S-WEBHOOK-'.fake()->unique()->numberBetween(1000, 9999),
        'payment_status' => Sale::PAYMENT_STATUS_PENDING,
        'paid_amount' => 0,
        'pending_amount' => 100,
        'subtotal' => 100,
        'discount' => 0,
        'total' => 100,
        'sold_at' => now(),
    ]);
    $payment = Payment::query()->create([
        'business_id' => $business->id,
        'sale_id' => $sale->id,
        'created_by' => $user->id,
        'method' => Payment::METHOD_DEBIT_CARD,
        'provider' => PaymentProvider::MercadoPago->value,
        'status' => PaymentStatus::Pending->value,
        'amount' => 100,
        'currency' => 'ARS',
        'provider_order_id' => $orderId,
        'requested_at' => now(),
    ]);

    return [$payment, [
        'id' => $notificationId,
        'action' => 'order.processed',
        'data' => ['id' => $orderId],
    ]];
}
