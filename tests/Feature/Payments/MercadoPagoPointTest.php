<?php

use App\Models\Business;
use App\Models\BusinessMercadoPagoCredential;
use App\Models\Branch;
use App\Models\BranchMercadoPagoPointSetting;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\Fiscal\FiscalSaleDocumentService;
use App\Services\SaleService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

test('authenticated business user can create a Mercado Pago Point order for a pending sale', function () {
    $business = Business::factory()->create();
    createMercadoPagoCredential($business);

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
            && $request->hasHeader('X-Idempotency-Key', "mp-point-b{$business->id}-s{$sale->id}-p{$payment->id}")
            && $payload['type'] === 'point'
            && $payload['external_reference'] === $payment->external_reference
            && $payload['transactions']['payments'][0]['amount'] === '1250.00'
            && $payload['config']['point']['terminal_id'] === 'NEWLAND_N950__SBX0000001'
            && $payload['config']['payment_method']['default_type'] === 'credit_card';
    });
});

test('business user can create a sale and send a QR order to Mercado Pago Point from POS', function () {
    $business = Business::factory()->create();
    createMercadoPagoCredential($business);

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

test('Mercado Pago Point reserves stock and consumes it only once after approval', function () {
    $business = Business::factory()->create();
    createMercadoPagoCredential($business);

    $admin = User::factory()->businessAdmin($business->id)->create();
    $product = createPointProduct($business, ['stock' => 5]);

    Http::fake([
        'https://api.mercadopago.com/v1/orders' => Http::response([
            'id' => 'ORD01RESERVED',
            'status' => 'created',
            'transactions' => ['payments' => [['id' => 'PAY01RESERVED']]],
        ], 201),
    ]);

    $this->actingAs($admin)->post(route('sales.store'), [
        'payment_status' => Sale::PAYMENT_STATUS_PAID,
        'payment_provider' => 'mercadopago_point',
        'payment_method' => Payment::METHOD_QR,
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 1000,
        ]],
    ])->assertRedirect();

    $sale = Sale::query()->firstOrFail();
    $payment = Payment::query()->firstOrFail();

    expect($sale->stock_reservation_status)->toBe(Sale::STOCK_RESERVATION_RESERVED);
    expect((float) $product->fresh()->stock)->toBe(5.0);
    expect((float) $product->fresh()->reserved_stock)->toBe(1.0);
    expect(fn () => app(FiscalSaleDocumentService::class)->issue($sale))
        ->toThrow(ValidationException::class);

    $payload = [
        'id' => 'notification-reserved-approved',
        'action' => 'order.processed',
        'type' => 'order',
        'data' => [
            'id' => 'ORD01RESERVED',
            'external_reference' => $payment->external_reference,
            'status' => 'processed',
            'transactions' => ['payments' => [['id' => 'PAY01RESERVED', 'status' => 'processed']]],
        ],
    ];

    $headers = signedMercadoPagoHeaders('ORD01RESERVED', 'request-reserved-approved');

    $this->postJson(route('webhooks.mercadopago.orders', ['data.id' => 'ORD01RESERVED']), $payload, $headers)->assertOk();
    $this->postJson(route('webhooks.mercadopago.orders', ['data.id' => 'ORD01RESERVED']), $payload, $headers)->assertOk();

    expect($sale->fresh()->stock_reservation_status)->toBe(Sale::STOCK_RESERVATION_CONSUMED);
    expect((float) $product->fresh()->stock)->toBe(4.0);
    expect((float) $product->fresh()->reserved_stock)->toBe(0.0);
    expect(\App\Models\StockMovement::query()->count())->toBe(1);
});

test('Mercado Pago Point releases reserved stock when the order is rejected', function () {
    $business = Business::factory()->create();
    createMercadoPagoCredential($business);

    $admin = User::factory()->businessAdmin($business->id)->create();
    $product = createPointProduct($business, ['stock' => 5]);

    Http::fake([
        'https://api.mercadopago.com/v1/orders' => Http::response([
            'id' => 'ORD01REJECTED',
            'status' => 'created',
            'transactions' => ['payments' => [['id' => 'PAY01REJECTED']]],
        ], 201),
    ]);

    $this->actingAs($admin)->post(route('sales.store'), [
        'payment_status' => Sale::PAYMENT_STATUS_PAID,
        'payment_provider' => 'mercadopago_point',
        'payment_method' => Payment::METHOD_DEBIT_CARD,
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 1000,
        ]],
    ])->assertRedirect();

    $payment = Payment::query()->firstOrFail();
    $payload = [
        'id' => 'notification-reserved-rejected',
        'action' => 'order.failed',
        'type' => 'order',
        'data' => [
            'id' => 'ORD01REJECTED',
            'external_reference' => $payment->external_reference,
            'status' => 'failed',
            'transactions' => ['payments' => [['id' => 'PAY01REJECTED', 'status' => 'failed']]],
        ],
    ];

    $this->postJson(
        route('webhooks.mercadopago.orders', ['data.id' => 'ORD01REJECTED']),
        $payload,
        signedMercadoPagoHeaders('ORD01REJECTED', 'request-reserved-rejected')
    )->assertOk();

    expect(Sale::query()->firstOrFail()->stock_reservation_status)->toBe(Sale::STOCK_RESERVATION_RELEASED);
    expect((float) $product->fresh()->stock)->toBe(5.0);
    expect((float) $product->fresh()->reserved_stock)->toBe(0.0);
    expect(\App\Models\StockMovement::query()->count())->toBe(0);
});

test('Mercado Pago Point creates a new idempotent attempt after a terminal payment status', function (string $status, string $providerStatus) {
    $business = Business::factory()->create();
    createMercadoPagoCredential($business);
    $admin = User::factory()->businessAdmin($business->id)->create();
    $sale = createMercadoPagoPointSale($business, $admin, 500);
    $sale->update(['point_status' => $status === Payment::STATUS_REJECTED
        ? Sale::POINT_STATUS_REJECTED
        : ($providerStatus === 'expired' ? Sale::POINT_STATUS_EXPIRED : Sale::POINT_STATUS_CANCELLED)]);

    $previous = Payment::query()->create([
        'business_id' => $business->id,
        'sale_id' => $sale->id,
        'created_by' => $admin->id,
        'method' => Payment::METHOD_DEBIT_CARD,
        'provider' => Payment::PROVIDER_MERCADOPAGO,
        'status' => $status,
        'amount' => 500,
        'currency' => 'ARS',
        'idempotency_key' => 'previous-point-attempt-'.$status,
        'provider_status' => $providerStatus,
        'requested_at' => now()->subMinute(),
        'rejected_at' => $status === Payment::STATUS_REJECTED ? now() : null,
        'cancelled_at' => $status === Payment::STATUS_CANCELLED ? now() : null,
    ]);

    Http::fake([
        'https://api.mercadopago.com/v1/orders' => Http::response([
            'id' => 'ORD01RETRY'.strtoupper(substr($status, 0, 3)),
            'status' => 'created',
            'transactions' => ['payments' => [['id' => 'PAY01RETRY']]],
        ], 201),
    ]);

    $this->actingAs($admin)->post(route('sales.payments.mercadopago-point.store', $sale), [
        'payment_method' => Payment::METHOD_DEBIT_CARD,
    ])->assertRedirect(route('sales.show', $sale));

    $retry = Payment::query()->latest('id')->firstOrFail();

    expect(Payment::query()->count())->toBe(2);
    expect($retry->id)->not->toBe($previous->id);
    expect($retry->status)->toBe(Payment::STATUS_PENDING);
    expect($retry->idempotency_key)->toBe("mp-point-b{$business->id}-s{$sale->id}-p{$retry->id}");
    expect($retry->idempotency_key)->not->toBe($previous->idempotency_key);
})->with([
    'rejected' => [Payment::STATUS_REJECTED, 'failed'],
    'cancelled' => [Payment::STATUS_CANCELLED, 'canceled'],
    'expired' => [Payment::STATUS_CANCELLED, 'expired'],
]);

test('manual cancellation releases a Point reservation only once', function () {
    $business = Business::factory()->create();
    createMercadoPagoCredential($business);
    $admin = User::factory()->businessAdmin($business->id)->create();
    $product = createPointProduct($business, ['stock' => 5]);

    Http::fake([
        'https://api.mercadopago.com/v1/orders' => Http::response([
            'id' => 'ORD01MANUALCANCEL',
            'status' => 'created',
            'transactions' => ['payments' => [['id' => 'PAY01MANUALCANCEL']]],
        ], 201),
        'https://api.mercadopago.com/v1/orders/ORD01MANUALCANCEL/cancel' => Http::response([
            'id' => 'ORD01MANUALCANCEL',
            'status' => 'canceled',
        ]),
    ]);

    $this->actingAs($admin)->post(route('sales.store'), pointSalePayload($product))->assertRedirect();

    $sale = Sale::query()->firstOrFail();
    $payment = Payment::query()->firstOrFail();

    $this->actingAs($admin)->post(route('sales.payments.mercadopago-point.cancel', [
        'sale' => $sale,
        'payment' => $payment,
    ]))->assertRedirect(route('sales.create'));

    $this->actingAs($admin)->post(route('sales.payments.mercadopago-point.cancel', [
        'sale' => $sale,
        'payment' => $payment,
    ]))->assertRedirect(route('sales.create'));

    expect($payment->fresh()->status)->toBe(Payment::STATUS_CANCELLED);
    expect($payment->fresh()->cancelled_at)->not->toBeNull();
    expect(data_get($payment->fresh()->metadata, 'cancellation_reason'))->toBe('user_cancelled');
    expect($sale->fresh()->point_status)->toBe(Sale::POINT_STATUS_CANCELLED);
    expect($sale->fresh()->stock_reservation_status)->toBe(Sale::STOCK_RESERVATION_RELEASED);
    expect((float) $product->fresh()->stock)->toBe(5.0);
    expect((float) $product->fresh()->reserved_stock)->toBe(0.0);
    Http::assertSentCount(2);
});

test('Point timeout releases an order creation reservation without an external order', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $product = createPointProduct($business, ['stock' => 5]);
    $sale = createReservedPointSale($business, $admin, $product);
    $payment = createPendingPointPayment($business, $admin, $sale, [
        'requested_at' => now()->subMinutes(11),
    ]);

    $this->artisan('payments:expire-mercadopago-point-reservations')->assertSuccessful();

    expect($payment->fresh()->status)->toBe(Payment::STATUS_CANCELLED);
    expect(data_get($payment->fresh()->metadata, 'cancellation_reason'))->toBe('order_creation_failed_timeout');
    expect($sale->fresh()->point_status)->toBe(Sale::POINT_STATUS_EXPIRED);
    expect($sale->fresh()->stock_reservation_status)->toBe(Sale::STOCK_RESERVATION_RELEASED);
    expect((float) $product->fresh()->stock)->toBe(5.0);
    expect((float) $product->fresh()->reserved_stock)->toBe(0.0);
    expect(fn () => app(FiscalSaleDocumentService::class)->issue($sale->fresh()))
        ->toThrow(ValidationException::class);
});

test('Point timeout confirms an approved remote order instead of releasing it', function () {
    $business = Business::factory()->create();
    createMercadoPagoCredential($business);
    $admin = User::factory()->businessAdmin($business->id)->create();
    $product = createPointProduct($business, ['stock' => 5]);
    $sale = createReservedPointSale($business, $admin, $product);
    $payment = createPendingPointPayment($business, $admin, $sale, [
        'provider_order_id' => 'ORD01TIMEOUTAPPROVED',
        'requested_at' => now()->subMinutes(11),
    ]);

    Http::fake([
        'https://api.mercadopago.com/v1/orders/ORD01TIMEOUTAPPROVED' => Http::response([
            'id' => 'ORD01TIMEOUTAPPROVED',
            'status' => 'processed',
            'transactions' => ['payments' => [['id' => 'PAY01TIMEOUTAPPROVED', 'status' => 'processed']]],
        ]),
    ]);

    $this->artisan('payments:expire-mercadopago-point-reservations')->assertSuccessful();

    expect($payment->fresh()->status)->toBe(Payment::STATUS_APPROVED);
    expect($sale->fresh()->point_status)->toBe(Sale::POINT_STATUS_APPROVED);
    expect($sale->fresh()->stock_reservation_status)->toBe(Sale::STOCK_RESERVATION_CONSUMED);
    expect((float) $product->fresh()->stock)->toBe(4.0);
    expect((float) $product->fresh()->reserved_stock)->toBe(0.0);
});

test('late remote approval after manual Point cancellation requires reconciliation without consuming stock', function () {
    $business = Business::factory()->create();
    createMercadoPagoCredential($business);
    $admin = User::factory()->businessAdmin($business->id)->create();
    $product = createPointProduct($business, ['stock' => 5]);
    $sale = createReservedPointSale($business, $admin, $product);
    $payment = createPendingPointPayment($business, $admin, $sale, [
        'provider_order_id' => 'ORD01OLDCANCELLED',
        'external_reference' => "b{$business->id}-s{$sale->id}-p1",
    ]);

    app(\App\Services\Payments\MercadoPago\MercadoPagoPaymentCompletionService::class)
        ->cancel($payment, 'user_cancelled');

    $this->postJson(route('webhooks.mercadopago.orders', ['data.id' => 'ORD01OLDCANCELLED']), [
        'id' => 'notification-old-cancelled',
        'action' => 'order.processed',
        'type' => 'order',
        'data' => [
            'id' => 'ORD01OLDCANCELLED',
            'external_reference' => $payment->external_reference,
            'status' => 'processed',
            'transactions' => ['payments' => [['id' => 'PAY01OLDCANCELLED', 'status' => 'processed']]],
        ],
    ], signedMercadoPagoHeaders('ORD01OLDCANCELLED', 'request-old-cancelled'))->assertOk();

    expect($payment->fresh()->status)->toBe(Payment::STATUS_APPROVED);
    expect($payment->fresh()->provider_payment_id)->toBe('PAY01OLDCANCELLED');
    expect(data_get($payment->fresh()->metadata, 'reconciliation.reason'))->toBe('remote_approved_after_local_cancellation');
    expect($sale->fresh()->point_status)->toBe(Sale::POINT_STATUS_RECONCILIATION_REQUIRED);
    expect($sale->fresh()->point_status_reason)->toBe('remote_approved_after_local_cancellation');
    expect((float) $product->fresh()->stock)->toBe(5.0);
    expect((float) $product->fresh()->reserved_stock)->toBe(0.0);
    expect(\App\Models\StockMovement::query()->count())->toBe(0);
    expect(fn () => app(FiscalSaleDocumentService::class)->issue($sale->fresh()))
        ->toThrow(ValidationException::class);
});

test('late remote approval after Point expiration requires reconciliation without consuming stock', function () {
    $business = Business::factory()->create();
    createMercadoPagoCredential($business);
    $admin = User::factory()->businessAdmin($business->id)->create();
    $product = createPointProduct($business, ['stock' => 5]);
    $sale = createReservedPointSale($business, $admin, $product);
    $payment = createPendingPointPayment($business, $admin, $sale, [
        'provider_order_id' => 'ORD01OLDEXPIRED',
        'external_reference' => "b{$business->id}-s{$sale->id}-p1",
    ]);

    app(\App\Services\Payments\MercadoPago\MercadoPagoPaymentCompletionService::class)
        ->cancel($payment, 'point_timeout', Sale::POINT_STATUS_EXPIRED);

    $this->postJson(route('webhooks.mercadopago.orders', ['data.id' => 'ORD01OLDEXPIRED']), [
        'id' => 'notification-old-expired',
        'action' => 'order.processed',
        'type' => 'order',
        'data' => [
            'id' => 'ORD01OLDEXPIRED',
            'external_reference' => $payment->external_reference,
            'status' => 'processed',
            'transactions' => ['payments' => [['id' => 'PAY01OLDEXPIRED', 'status' => 'processed']]],
        ],
    ], signedMercadoPagoHeaders('ORD01OLDEXPIRED', 'request-old-expired'))->assertOk();

    expect($payment->fresh()->status)->toBe(Payment::STATUS_APPROVED);
    expect(data_get($payment->fresh()->metadata, 'reconciliation.reason'))->toBe('remote_approved_after_expiration');
    expect($sale->fresh()->point_status)->toBe(Sale::POINT_STATUS_RECONCILIATION_REQUIRED);
    expect($sale->fresh()->stock_reservation_status)->toBe(Sale::STOCK_RESERVATION_RELEASED);
    expect((float) $product->fresh()->stock)->toBe(5.0);
    expect((float) $product->fresh()->reserved_stock)->toBe(0.0);
    expect(\App\Models\StockMovement::query()->count())->toBe(0);
});

test('late rejected webhook over a locally cancelled Point payment stays terminal without a reconciliation conflict', function () {
    $business = Business::factory()->create();
    createMercadoPagoCredential($business);
    $admin = User::factory()->businessAdmin($business->id)->create();
    $product = createPointProduct($business, ['stock' => 5]);
    $sale = createReservedPointSale($business, $admin, $product);
    $payment = createPendingPointPayment($business, $admin, $sale, [
        'provider_order_id' => 'ORD01LATEFAILED',
        'external_reference' => "b{$business->id}-s{$sale->id}-p1",
    ]);

    app(\App\Services\Payments\MercadoPago\MercadoPagoPaymentCompletionService::class)
        ->cancel($payment, 'user_cancelled');

    $this->postJson(route('webhooks.mercadopago.orders', ['data.id' => 'ORD01LATEFAILED']), [
        'id' => 'notification-late-failed',
        'action' => 'order.failed',
        'type' => 'order',
        'data' => [
            'id' => 'ORD01LATEFAILED',
            'external_reference' => $payment->external_reference,
            'status' => 'failed',
            'transactions' => ['payments' => [['id' => 'PAY01LATEFAILED', 'status' => 'failed']]],
        ],
    ], signedMercadoPagoHeaders('ORD01LATEFAILED', 'request-late-failed'))->assertOk();

    expect($payment->fresh()->status)->toBe(Payment::STATUS_CANCELLED);
    expect($payment->fresh()->provider_status)->toBe('failed');
    expect(data_get($payment->fresh()->metadata, 'reconciliation'))->toBeNull();
    expect($sale->fresh()->point_status)->toBe(Sale::POINT_STATUS_CANCELLED);
    expect((float) $product->fresh()->stock)->toBe(5.0);
    expect(\App\Models\StockMovement::query()->count())->toBe(0);
});

test('Mercado Pago Point uses enabled business credentials before global config', function () {
    config()->set('services.mercadopago.access_token', 'APP_USR-global-token');
    config()->set('services.mercadopago.point_terminal_id', 'NEWLAND_N950__GLOBAL');

    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $sale = createMercadoPagoPointSale($business, $admin, 640);

    createMercadoPagoCredential($business, [
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

test('Mercado Pago Point uses the terminal configured for the sale branch', function () {
    $business = Business::factory()->create();
    $branch = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Sucursal Centro',
        'code' => 'centro',
        'is_active' => true,
        'is_default' => false,
    ]);
    $admin = User::factory()->businessAdmin($business->id)->create();
    createMercadoPagoCredential($business, ['point_terminal_id' => 'NEWLAND_N950__FALLBACK']);
    BranchMercadoPagoPointSetting::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'is_enabled' => true,
        'point_terminal_id' => 'NEWLAND_N950__CENTRO',
    ]);
    $sale = Sale::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'user_id' => $admin->id,
        'sale_number' => 'S-MP-CENTRO',
        'payment_status' => Sale::PAYMENT_STATUS_PENDING,
        'paid_amount' => 0,
        'pending_amount' => 640,
        'subtotal' => 640,
        'discount' => 0,
        'total' => 640,
        'sold_at' => now(),
    ]);

    Http::fake(['https://api.mercadopago.com/v1/orders' => Http::response([
        'id' => 'ORD01BRANCH',
        'status' => 'created',
        'transactions' => ['payments' => [['id' => 'PAY01BRANCH', 'amount' => '640.00']]],
    ], 201)]);

    $this->actingAs($admin)
        ->withSession(['branch_id' => $branch->id])
        ->post(route('sales.payments.mercadopago-point.store', $sale), ['payment_method' => Payment::METHOD_DEBIT_CARD])
        ->assertRedirect(route('sales.show', $sale));

    Http::assertSent(fn (Request $request): bool => data_get($request->data(), 'config.point.terminal_id') === 'NEWLAND_N950__CENTRO');
});

test('Mercado Pago Point does not use global credentials when business credentials are inactive', function () {
    config()->set('services.mercadopago.access_token', 'APP_USR-global-token');
    config()->set('services.mercadopago.point_terminal_id', 'NEWLAND_N950__GLOBAL');

    $business = Business::factory()->create();
    createMercadoPagoCredential($business, ['is_enabled' => false]);

    $admin = User::factory()->businessAdmin($business->id)->create();
    $sale = createMercadoPagoPointSale($business, $admin, 640);

    Http::fake();

    $this
        ->actingAs($admin)
        ->post(route('sales.payments.mercadopago-point.store', $sale), [
            'payment_method' => Payment::METHOD_DEBIT_CARD,
        ])
        ->assertSessionHasErrors('mercadopago_point');

    expect(Payment::query()->count())->toBe(0);
    Http::assertNothingSent();
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
    createMercadoPagoCredential($business);

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
    $business = Business::factory()->create();
    createMercadoPagoCredential($business);

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

test('Mercado Pago webhook rejects payloads with crossed business references', function () {
    $victimBusiness = Business::factory()->create();
    createMercadoPagoCredential($victimBusiness, ['webhook_secret' => 'victim-webhook-secret']);

    $attackerBusiness = Business::factory()->create();
    createMercadoPagoCredential($attackerBusiness, ['webhook_secret' => 'attacker-webhook-secret']);

    $victimAdmin = User::factory()->businessAdmin($victimBusiness->id)->create();
    $attackerAdmin = User::factory()->businessAdmin($attackerBusiness->id)->create();
    $victimSale = createMercadoPagoPointSale($victimBusiness, $victimAdmin, 1200);
    $attackerSale = createMercadoPagoPointSale($attackerBusiness, $attackerAdmin, 800);

    $victimPayment = Payment::query()->create([
        'business_id' => $victimBusiness->id,
        'sale_id' => $victimSale->id,
        'created_by' => $victimAdmin->id,
        'method' => Payment::METHOD_DEBIT_CARD,
        'provider' => Payment::PROVIDER_MERCADOPAGO,
        'status' => Payment::STATUS_PENDING,
        'amount' => 1200,
        'currency' => 'ARS',
        'external_reference' => "b{$victimBusiness->id}-s{$victimSale->id}-p1",
        'provider_order_id' => 'ORD01VICTIM',
        'requested_at' => now(),
    ]);

    $attackerPayment = Payment::query()->create([
        'business_id' => $attackerBusiness->id,
        'sale_id' => $attackerSale->id,
        'created_by' => $attackerAdmin->id,
        'method' => Payment::METHOD_DEBIT_CARD,
        'provider' => Payment::PROVIDER_MERCADOPAGO,
        'status' => Payment::STATUS_PENDING,
        'amount' => 800,
        'currency' => 'ARS',
        'external_reference' => "b{$attackerBusiness->id}-s{$attackerSale->id}-p1",
        'provider_order_id' => 'ORD01ATTACKER',
        'requested_at' => now(),
    ]);

    $this
        ->postJson(route('webhooks.mercadopago.orders', [
            'data.id' => 'ORD01ATTACKER',
            'type' => 'order',
        ]), [
            'id' => 'notification-attacker',
            'action' => 'order.processed',
            'type' => 'order',
            'data' => [
                'id' => 'ORD01ATTACKER',
                'external_reference' => $victimPayment->external_reference,
                'status' => 'processed',
                'transactions' => [
                    'payments' => [
                        [
                            'id' => 'PAY01ATTACKER',
                            'amount' => '800.00',
                            'paid_amount' => '800.00',
                            'status' => 'processed',
                        ],
                    ],
                ],
            ],
        ], signedMercadoPagoHeaders('ORD01ATTACKER', 'request-attacker', 'attacker-webhook-secret'))
        ->assertUnauthorized();

    expect($victimPayment->fresh()->status)->toBe(Payment::STATUS_PENDING);
    expect($victimSale->fresh()->payment_status)->toBe(Sale::PAYMENT_STATUS_PENDING);
    expect($attackerPayment->fresh()->status)->toBe(Payment::STATUS_PENDING);
    expect($attackerSale->fresh()->payment_status)->toBe(Sale::PAYMENT_STATUS_PENDING);
    expect(PaymentEvent::query()->count())->toBe(0);
});

test('Mercado Pago minimal webhook fetches order details before syncing payment', function () {
    $business = Business::factory()->create();
    createMercadoPagoCredential($business);

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

/**
 * @param  array<string, mixed>  $overrides
 */
function createMercadoPagoCredential(Business $business, array $overrides = []): BusinessMercadoPagoCredential
{
    return BusinessMercadoPagoCredential::query()->create([
        'business_id' => $business->id,
        'is_enabled' => true,
        'environment' => 'testing',
        'public_key' => 'APP_USR-public-test',
        'access_token' => 'APP_USR-testing-token',
        'webhook_secret' => 'testing-webhook-secret',
        'point_terminal_id' => 'NEWLAND_N950__SBX0000001',
        'point_expiration_time' => 'PT15M',
        'point_print_on_terminal' => 'no_ticket',
        ...$overrides,
    ]);
}

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
 * @return array<string, mixed>
 */
function pointSalePayload(Product $product): array
{
    return [
        'payment_status' => Sale::PAYMENT_STATUS_PAID,
        'payment_provider' => 'mercadopago_point',
        'payment_method' => Payment::METHOD_DEBIT_CARD,
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => $product->sale_price,
        ]],
    ];
}

function createReservedPointSale(Business $business, User $user, Product $product): Sale
{
    return app(SaleService::class)->createSale($business, $user, pointSalePayload($product));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function createPendingPointPayment(Business $business, User $user, Sale $sale, array $overrides = []): Payment
{
    return Payment::query()->create([
        'business_id' => $business->id,
        'sale_id' => $sale->id,
        'created_by' => $user->id,
        'method' => Payment::METHOD_DEBIT_CARD,
        'provider' => Payment::PROVIDER_MERCADOPAGO,
        'status' => Payment::STATUS_PENDING,
        'amount' => $sale->total,
        'currency' => 'ARS',
        'requested_at' => now(),
        ...$overrides,
    ]);
}

/**
 * @return array<string, string>
 */
function signedMercadoPagoHeaders(string $orderId, string $requestId, string $secret = 'testing-webhook-secret'): array
{
    $timestamp = '1234567890';
    $manifest = 'id:'.strtolower($orderId).';request-id:'.$requestId.';ts:'.$timestamp.';';
    $signature = hash_hmac('sha256', $manifest, $secret);

    return [
        'x-request-id' => $requestId,
        'x-signature' => 'ts='.$timestamp.',v1='.$signature,
    ];
}
