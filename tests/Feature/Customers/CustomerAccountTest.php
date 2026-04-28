<?php

use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;

test('partial sale creates pending balance and debt movement for the selected customer', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $customer = Customer::factory()->create([
        'business_id' => $business->id,
    ]);
    $product = Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Yerba',
        'slug' => 'yerba-cliente-parcial',
        'unit_type' => 'unit',
        'sale_price' => 1000,
        'cost_price' => 600,
        'stock' => 10,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    $this
        ->actingAs($admin)
        ->post('/sales', [
            'customer_id' => $customer->id,
            'payment_status' => 'partial',
            'payment_method' => 'cash',
            'paid_amount' => 400,
            'amount_received' => 500,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 1000,
            ]],
        ])
        ->assertRedirect();

    $sale = Sale::query()->firstOrFail();
    $movement = CustomerAccountMovement::query()->firstOrFail();

    expect($sale->customer_id)->toBe($customer->id);
    expect($sale->payment_status)->toBe(Sale::PAYMENT_STATUS_PARTIAL);
    expect((float) $sale->paid_amount)->toBe(400.0);
    expect((float) $sale->pending_amount)->toBe(600.0);
    expect((float) $sale->amount_received)->toBe(500.0);
    expect((float) $sale->change_amount)->toBe(100.0);
    expect($movement->type)->toBe(CustomerAccountMovement::TYPE_DEBT);
    expect((float) $movement->amount)->toBe(600.0);
    expect((float) $movement->balance_after)->toBe(600.0);
    expect($movement->sale_id)->toBe($sale->id);
});

test('fiado sale requires a customer', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $product = Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Azucar',
        'slug' => 'azucar-fiado',
        'unit_type' => 'unit',
        'sale_price' => 1200,
        'cost_price' => 700,
        'stock' => 10,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    $this
        ->actingAs($admin)
        ->from('/sales/create')
        ->post('/sales', [
            'payment_status' => 'pending',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 1200,
            ]],
        ])
        ->assertSessionHasErrors('customer_id');
});

test('customer payment applies to oldest open sales and updates pending balances', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $customer = Customer::factory()->create([
        'business_id' => $business->id,
    ]);
    $product = Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Galletitas',
        'slug' => 'galletitas-pagos-cliente',
        'unit_type' => 'unit',
        'sale_price' => 1000,
        'cost_price' => 600,
        'stock' => 20,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($admin)->post('/sales', [
        'customer_id' => $customer->id,
        'payment_status' => 'pending',
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 1000,
        ]],
    ])->assertRedirect();

    $this->actingAs($admin)->post('/sales', [
        'customer_id' => $customer->id,
        'payment_status' => 'pending',
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 700,
        ]],
    ])->assertRedirect();

    $this
        ->actingAs($admin)
        ->from("/customers/{$customer->id}")
        ->post("/customers/{$customer->id}/payments", [
            'amount' => 1200,
            'paid_at' => now()->toDateTimeString(),
            'payment_method' => 'cash',
            'description' => 'Pago parcial del cliente',
        ])
        ->assertRedirect("/customers/{$customer->id}");

    $sales = Sale::query()->forBusiness($business->id)->orderBy('id')->get();
    $paymentMovement = CustomerAccountMovement::query()
        ->where('type', CustomerAccountMovement::TYPE_PAYMENT)
        ->firstOrFail();

    expect($sales[0]->payment_status)->toBe(Sale::PAYMENT_STATUS_PAID);
    expect((float) $sales[0]->pending_amount)->toBe(0.0);
    expect((float) $sales[0]->paid_amount)->toBe(1000.0);
    expect($sales[1]->payment_status)->toBe(Sale::PAYMENT_STATUS_PARTIAL);
    expect((float) $sales[1]->pending_amount)->toBe(500.0);
    expect((float) $sales[1]->paid_amount)->toBe(200.0);
    expect((float) $paymentMovement->balance_after)->toBe(500.0);
    expect($paymentMovement->meta['allocations'])->toHaveCount(2);
});

test('customer payment can not exceed the current pending balance', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $customer = Customer::factory()->create([
        'business_id' => $business->id,
    ]);
    $product = Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Aceite',
        'slug' => 'aceite-pago-excedido',
        'unit_type' => 'unit',
        'sale_price' => 1800,
        'cost_price' => 1200,
        'stock' => 10,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($admin)->post('/sales', [
        'customer_id' => $customer->id,
        'payment_status' => 'pending',
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 1800,
        ]],
    ])->assertRedirect();

    $this
        ->actingAs($admin)
        ->from("/customers/{$customer->id}")
        ->post("/customers/{$customer->id}/payments", [
            'amount' => 2500,
            'paid_at' => now()->toDateTimeString(),
            'payment_method' => 'transfer',
        ])
        ->assertSessionHasErrors('amount');

    expect(CustomerAccountMovement::query()->where('type', CustomerAccountMovement::TYPE_PAYMENT)->count())->toBe(0);
});
