<?php

use App\Models\Business;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\Payments\PaymentService;

test('sale creation stores an approved manual payment', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $product = createPaymentTestProduct($business, [
        'sale_price' => 1000,
        'stock' => 5,
    ]);

    $this
        ->actingAs($admin)
        ->post('/sales', [
            'payment_method' => Payment::METHOD_CASH,
            'amount_received' => 1200,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 1000,
            ]],
        ])
        ->assertRedirect();

    $sale = Sale::query()->firstOrFail();
    $payment = Payment::query()->firstOrFail();

    expect($payment->business_id)->toBe($business->id);
    expect($payment->sale_id)->toBe($sale->id);
    expect($payment->method)->toBe(Payment::METHOD_CASH);
    expect($payment->provider)->toBe(Payment::PROVIDER_MANUAL);
    expect($payment->status)->toBe(Payment::STATUS_APPROVED);
    expect((float) $payment->amount)->toBe(1000.0);
    expect((float) $sale->paid_amount)->toBe(1000.0);
    expect((float) $sale->pending_amount)->toBe(0.0);
});

test('customer account payment creates manual payments for allocated sales', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $customer = Customer::factory()->create([
        'business_id' => $business->id,
    ]);
    $product = createPaymentTestProduct($business, [
        'sale_price' => 1000,
        'stock' => 10,
    ]);

    foreach ([1000, 700] as $price) {
        $this->actingAs($admin)->post('/sales', [
            'customer_id' => $customer->id,
            'payment_status' => Sale::PAYMENT_STATUS_PENDING,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => $price,
            ]],
        ])->assertRedirect();
    }

    $this
        ->actingAs($admin)
        ->post("/customers/{$customer->id}/payments", [
            'amount' => 1200,
            'payment_method' => Payment::METHOD_DEBIT_CARD,
            'paid_at' => now()->toDateTimeString(),
        ])
        ->assertRedirect("/customers/{$customer->id}");

    $sales = Sale::query()->forBusiness($business->id)->orderBy('id')->get();
    $payments = Payment::query()->forBusiness($business->id)->orderBy('id')->get();

    expect($payments)->toHaveCount(2);
    expect($payments->pluck('method')->all())->toBe([
        Payment::METHOD_DEBIT_CARD,
        Payment::METHOD_DEBIT_CARD,
    ]);
    expect($payments->map(fn (Payment $payment): float => (float) $payment->amount)->all())->toBe([1000.0, 200.0]);
    expect($sales[0]->payment_status)->toBe(Sale::PAYMENT_STATUS_PAID);
    expect((float) $sales[1]->paid_amount)->toBe(200.0);
    expect((float) $sales[1]->pending_amount)->toBe(500.0);
});

test('manual payment idempotency prevents duplicated approved payments', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $customer = Customer::factory()->create([
        'business_id' => $business->id,
    ]);
    $product = createPaymentTestProduct($business, [
        'sale_price' => 500,
        'stock' => 3,
    ]);

    $this->actingAs($admin)->post('/sales', [
        'customer_id' => $customer->id,
        'payment_status' => Sale::PAYMENT_STATUS_PENDING,
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 500,
        ]],
    ])->assertRedirect();

    $sale = Sale::query()->firstOrFail();
    $service = app(PaymentService::class);
    $idempotencyKey = 'manual-payment-test:'.$sale->id;

    $firstPayment = $service->createManualPaymentForSale(
        $business,
        $sale,
        $admin,
        Payment::METHOD_TRANSFER,
        500,
        null,
        $idempotencyKey
    );

    $secondPayment = $service->createManualPaymentForSale(
        $business,
        $sale,
        $admin,
        Payment::METHOD_TRANSFER,
        500,
        null,
        $idempotencyKey
    );

    expect($secondPayment->id)->toBe($firstPayment->id);
    expect(Payment::query()->count())->toBe(1);
    expect($sale->fresh()->payment_status)->toBe(Sale::PAYMENT_STATUS_PAID);
});

test('rejected payments do not mark sale as paid', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $sale = Sale::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'sale_number' => 'S-REJECTED-001',
        'payment_method' => null,
        'payment_status' => Sale::PAYMENT_STATUS_PENDING,
        'paid_amount' => 0,
        'pending_amount' => 1000,
        'subtotal' => 1000,
        'discount' => 0,
        'total' => 1000,
        'sold_at' => now(),
    ]);

    Payment::query()->create([
        'business_id' => $business->id,
        'sale_id' => $sale->id,
        'created_by' => $admin->id,
        'method' => Payment::METHOD_QR,
        'provider' => Payment::PROVIDER_MANUAL,
        'status' => Payment::STATUS_REJECTED,
        'amount' => 1000,
        'currency' => 'ARS',
        'rejected_at' => now(),
    ]);

    app(PaymentService::class)->syncSalePaymentSummary($sale);

    $sale->refresh();

    expect($sale->payment_status)->toBe(Sale::PAYMENT_STATUS_PENDING);
    expect((float) $sale->paid_amount)->toBe(0.0);
    expect((float) $sale->pending_amount)->toBe(1000.0);
});

function createPaymentTestProduct(Business $business, array $overrides = []): Product
{
    return Product::query()->create([
        'business_id' => $business->id,
        'name' => $overrides['name'] ?? 'Producto pago',
        'slug' => $overrides['slug'] ?? 'producto-pago-'.fake()->unique()->numberBetween(1000, 9999),
        'unit_type' => 'unit',
        'sale_price' => $overrides['sale_price'] ?? 1000,
        'cost_price' => $overrides['cost_price'] ?? 500,
        'stock' => $overrides['stock'] ?? 10,
        'min_stock' => 1,
        'is_active' => true,
    ]);
}
