<?php

use App\Models\Business;
use App\Models\BusinessMercadoPagoCredential;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('authenticated business user can create a Mercado Pago Point order for a pending sale', function () {
    config()->set('services.mercadopago.access_token', 'APP_USR-testing-token');
    config()->set('services.mercadopago.point_terminal_id', 'NEWLAND_N950__SBX0000001');

    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $sale = createMercadoPagoPointSale($business, $admin, 1250);

    Http::fake([
        'https://api.mercadopago.com/v1/orders' => Http::response([
            'id' => 'ORD01TESTPOINT',
            'status' => 'created',
            'transactions' => [
                'payments' => [
                    [
                        'id' => 'PAY01TESTPOINT',
                        'amount' => '1250.00',
                        'status' => 'created',
                    ],
                ],
            ],
        ], 201),
    ]);

    $this
        ->actingAs($admin)
        ->post(route('sales.payments.mercadopago-point.store', $sale), [
            'payment_method' => Payment::METHOD_CREDIT_CARD,
        ])
        ->assertRedirect(route('sales.show', $sale));

    $payment = Payment::query()->firstOrFail();

    expect($payment->provider)->toBe(Payment::PROVIDER_MERCADOPAGO);
    expect($payment->status)->toBe(Payment::STATUS_PENDING);
    expect($payment->provider_order_id)->toBe('ORD01TESTPOINT');
    expect($payment->provider_payment_id)->toBe('PAY01TESTPOINT');
    expect($payment->external_reference)->toBe("b{$business->id}-s{$sale->id}-p{$payment->id}");

    Http::assertSent(function (Request $request) use ($business, $sale, $payment): bool {
        $payload = $request->data();

        return $request->method() === 'POST'
            && $request->url() === 'https://api.mercadopago.com/v1/orders'
            && $request->hasHeader('Authorization', 'Bearer APP_USR-testing-token')
            && $request->hasHeader('X-Idempotency-Key', "mp-point-sale-{$business->id}-{$sale->id}")
            && $payload['type'] === 'point'
            && $payload['external_reference'] === $payment->external_reference
            && $payload['transactions']['payments'][0]['amount'] === '1250.00'
            && $payload['config']['point']['terminal_id'] === 'NEWLAND_N950__SBX0000001'
            && $payload['config']['payment_method']['default_type'] === 'credit_card';
    });
});

test('business user can create a sale and send a QR order to Mercado Pago Point from POS', function () {
    config()->set('services.mercadopago.access_token', 'APP_USR-testing-token');
    config()->set('services.mercadopago.point_terminal_id', 'NEWLAND_N950__SBX0000001');

    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $product = createPointProduct($business, ['sale_price' => 2100, 'stock' => 5]);

    Http::fake([
        'https://api.mercadopago.com/v1/orders' => Http::response([
            'id' => 'ORD01SALEQR',
            'status' => 'created',
            'transactions' => [
                'payments' => [
                    ['id' => 'PAY01SALEQR', 'amount' => '2100.00', 'status' => 'created'],
                ],
            ],
        ], 201),
    ]);

    $this
        ->actingAs($admin)
        ->post(route('sales.store'), [
            'payment_status' => Sale::PAYMENT_STATUS_PAID,
            'payment_provider' => 'mercadopago_point',
            'payment_method' => Payment::METHOD_QR,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 2100,
            ]],
        ])
        ->assertRedirect();

    $sale = Sale::query()->firstOrFail();
    $payment = Payment::query()->firstOrFail();

    expect($sale->payment_status)->toBe(Sale::PAYMENT_STATUS_PENDING);
    expect($sale->payment_method)->toBe(Payment::METHOD_QR);
    expect((float) $sale->paid_amount)->toBe(0.0);
    expect((float) $sale->pending_amount)->toBe(2100.0);
    expect($payment->provider)->toBe(Payment::PROVIDER_MERCADOPAGO);
    expect($payment->status)->toBe(Payment::STATUS_PENDING);
    expect($payment->provider_order_id)->toBe('ORD01SALEQR');

    Http::assertSent(function (Request $request): bool {
        $payload = $request->data();

        return $request->method() === 'POST'
            && $payload['config']['payment_method']['default_type'] === 'qr'
            && $payload['transactions']['payments'][0]['amount'] === '2100.00';
    });
});

test('Mercado Pago Point uses enabled business credentials before global config', function () {
    config()->set('services.mercadopago.access_token', 'APP_USR-global-token');
    config()->set('services.mercadopago.point_terminal_id', 'NEWLAND_N950__GLOBAL');

    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $sale = createMercadoPagoPointSale($business, $admin, 640);

    BusinessMercadoPagoCredential::query()->create([
        'business_id' => $business->id,
        'is_enabled' => true,
        'environment' => 'testing',
        'access_token' => 'APP_USR-business-token',
        'point_terminal_id' => 'NEWLAND_N950__BUSINESS',
    ]);

    Http::fake([
        'https://api.mercadopago.com/v1/orders' => Http::response([
            'id' => 'ORD01BUSINESS',
            'status' => 'created',
            'transactions' => [
                'payments' => [
                    ['id' => 'PAY01BUSINESS', 'amount' => '640.00', 'status' => 'created'],
                ],
            ],
        ], 201),
    ]);

    $this
        ->actingAs($admin)
        ->post(route('sales.payments.mercadopago-point.store', $sale), [
            'payment_method' => Payment::METHOD_DEBIT_CARD,
        ])
        ->assertRedirect(route('sales.show', $sale));

    Http::assertSent(function (Request $request): bool {
        $payload = $request->data();

        return $request->hasHeader('Authorization', 'Bearer APP_USR-business-token')
            && $payload['config']['point']['terminal_id'] === 'NEWLAND_N950__BUSINESS';
    });
});

test('business admin can store Mercado Pago Point credentials for their business', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();

    $this
        ->actingAs($admin)
        ->put(route('mercadopago-settings.update'), [
            'is_enabled' => true,
            'environment' => 'testing',
            'public_key' => 'APP_USR-public-test',
            'access_token' => 'APP_USR-private-test',
            'webhook_secret' => 'webhook-secret-test',
            'point_terminal_id' => 'NEWLAND_N950__SBX0000001',
            'point_store_id' => '86244114',
            'point_pos_id' => '136820601',
            'point_external_store_id' => 'SUC001',
            'point_external_pos_id' => 'CAJA001',
            'point_expiration_time' => 'PT15M',
            'point_print_on_terminal' => 'no_ticket',
        ])
        ->assertRedirect(route('mercadopago-settings.edit'));

    $credential = BusinessMercadoPagoCredential::query()->firstOrFail();

    expect($credential->business_id)->toBe($business->id);
    expect($credential->is_enabled)->toBeTrue();
    expect($credential->access_token)->toBe('APP_USR-private-test');
    expect($credential->webhook_secret)->toBe('webhook-secret-test');
    expect($credential->point_terminal_id)->toBe('NEWLAND_N950__SBX0000001');
});

test('Mercado Pago Point order creation reuses a pending provider payment', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $sale = createMercadoPagoPointSale($business, $admin, 500);

    Http::fake([
        'https://api.mercadopago.com/v1/orders' => Http::response([
            'id' => 'ORD01REUSED',
            'status' => 'created',
            'transactions' => [
                'payments' => [
                    ['id' => 'PAY01REUSED', 'amount' => '500.00', 'status' => 'created'],
                ],
            ],
        ], 201),
    ]);

    $this->actingAs($admin)->post(route('sales.payments.mercadopago-point.store', $sale))->assertRedirect();
    $this->actingAs($admin)->post(route('sales.payments.mercadopago-point.store', $sale))->assertRedirect();

    expect(Payment::query()->count())->toBe(1);
    Http::assertSentCount(1);
});

test('Mercado Pago order webhook approves payment and recalculates sale once', function () {
    config()->set('services.mercadopago.webhook_secret', 'testing-webhook-secret');

    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $sale = createMercadoPagoPointSale($business, $admin, 900);
    $payment = Payment::query()->create([
        'business_id' => $business->id,
        'sale_id' => $sale->id,
        'created_by' => $admin->id,
        'method' => Payment::METHOD_DEBIT_CARD,
        'provider' => Payment::PROVIDER_MERCADOPAGO,
        'status' => Payment::STATUS_PENDING,
        'amount' => 900,
        'currency' => 'ARS',
        'external_reference' => "b{$business->id}-s{$sale->id}-p1",
        'provider_order_id' => 'ORD01WEBHOOK',
        'requested_at' => now(),
    ]);

    $payload = [
        'id' => 'notification-processed-1',
        'action' => 'order.processed',
        'type' => 'order',
        'data' => [
            'id' => 'ORD01WEBHOOK',
            'external_reference' => $payment->external_reference,
            'status' => 'processed',
            'transactions' => [
                'payments' => [
                    [
                        'id' => 'PAY01WEBHOOK',
                        'amount' => '900.00',
                        'paid_amount' => '900.00',
                        'status' => 'processed',
                        'payment_method' => [
                            'type' => 'debit_card',
                        ],
                    ],
                ],
            ],
        ],
    ];
    $headers = signedMercadoPagoHeaders('ORD01WEBHOOK', 'request-1');

    $this
        ->postJson(route('webhooks.mercadopago.orders', [
            'data.id' => 'ORD01WEBHOOK',
            'type' => 'order',
        ]), $payload, $headers)
        ->assertOk();

    $this
        ->postJson(route('webhooks.mercadopago.orders', [
            'data.id' => 'ORD01WEBHOOK',
            'type' => 'order',
        ]), $payload, $headers)
        ->assertOk();

    expect($payment->fresh()->status)->toBe(Payment::STATUS_APPROVED);
    expect($payment->fresh()->provider_payment_id)->toBe('PAY01WEBHOOK');
    expect($sale->fresh()->payment_status)->toBe(Sale::PAYMENT_STATUS_PAID);
    expect((float) $sale->fresh()->paid_amount)->toBe(900.0);
    expect(PaymentEvent::query()->count())->toBe(1);
});

test('Mercado Pago minimal webhook fetches order details before syncing payment', function () {
    config()->set('services.mercadopago.webhook_secret', 'testing-webhook-secret');

    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $sale = createMercadoPagoPointSale($business, $admin, 700);
    Payment::query()->create([
        'business_id' => $business->id,
        'sale_id' => $sale->id,
        'created_by' => $admin->id,
        'method' => Payment::METHOD_CREDIT_CARD,
        'provider' => Payment::PROVIDER_MERCADOPAGO,
        'status' => Payment::STATUS_PENDING,
        'amount' => 700,
        'currency' => 'ARS',
        'provider_order_id' => 'ORD01MINIMAL',
        'requested_at' => now(),
    ]);

    Http::fake([
        'https://api.mercadopago.com/v1/orders/ORD01MINIMAL' => Http::response([
            'id' => 'ORD01MINIMAL',
            'status' => 'processed',
            'transactions' => [
                'payments' => [
                    ['id' => 'PAY01MINIMAL', 'amount' => '700.00', 'status' => 'processed'],
                ],
            ],
        ]),
    ]);

    $this
        ->postJson(route('webhooks.mercadopago.orders', [
            'data.id' => 'ORD01MINIMAL',
            'type' => 'order',
        ]), [
            'id' => 'notification-minimal-1',
            'action' => 'order.processed',
            'type' => 'order',
            'data' => [
                'id' => 'ORD01MINIMAL',
            ],
        ], signedMercadoPagoHeaders('ORD01MINIMAL', 'request-minimal'))
        ->assertOk();

    $payment = Payment::query()->firstOrFail();

    expect($payment->status)->toBe(Payment::STATUS_APPROVED);
    expect($payment->provider_payment_id)->toBe('PAY01MINIMAL');
    expect($sale->fresh()->payment_status)->toBe(Sale::PAYMENT_STATUS_PAID);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://api.mercadopago.com/v1/orders/ORD01MINIMAL');
});

test('Mercado Pago webhook rejects invalid signature', function () {
    config()->set('services.mercadopago.webhook_secret', 'testing-webhook-secret');

    $this
        ->postJson(route('webhooks.mercadopago.orders', [
            'data.id' => 'ORD01BAD',
            'type' => 'order',
        ]), [
            'id' => 'notification-invalid',
            'action' => 'order.processed',
            'data' => ['id' => 'ORD01BAD', 'status' => 'processed'],
        ], [
            'x-request-id' => 'request-bad',
            'x-signature' => 'ts=1234567890,v1=bad-signature',
        ])
        ->assertUnauthorized();

    expect(PaymentEvent::query()->count())->toBe(0);
});

function createMercadoPagoPointSale(Business $business, User $user, float $total): Sale
{
    return Sale::query()->create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'sale_number' => 'S-MP-'.fake()->unique()->numberBetween(1000, 9999),
        'payment_method' => null,
        'payment_status' => Sale::PAYMENT_STATUS_PENDING,
        'paid_amount' => 0,
        'pending_amount' => $total,
        'subtotal' => $total,
        'discount' => 0,
        'total' => $total,
        'sold_at' => now(),
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function createPointProduct(Business $business, array $overrides = []): Product
{
    return Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Producto Point '.fake()->unique()->numberBetween(1000, 9999),
        'slug' => 'producto-point-'.fake()->unique()->numberBetween(1000, 9999),
        'unit_type' => 'unit',
        'sale_price' => 1000,
        'cost_price' => 500,
        'vat_treatment' => 'gravado',
        'vat_rate' => 21,
        'stock' => 10,
        'min_stock' => 0,
        'is_active' => true,
        ...$overrides,
    ]);
}

/**
 * @return array<string, string>
 */
function signedMercadoPagoHeaders(string $orderId, string $requestId): array
{
    $timestamp = '1234567890';
    $manifest = 'id:'.strtolower($orderId).';request-id:'.$requestId.';ts:'.$timestamp.';';
    $signature = hash_hmac('sha256', $manifest, 'testing-webhook-secret');

    return [
        'x-request-id' => $requestId,
        'x-signature' => 'ts='.$timestamp.',v1='.$signature,
    ];
}
