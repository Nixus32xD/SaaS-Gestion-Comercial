<?php

use App\Models\Business;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;

test('sale decrements stock and stores business on items', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $product = Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Galletitas',
        'slug' => 'galletitas',
        'unit_type' => 'unit',
        'sale_price' => 1500,
        'cost_price' => 900,
        'stock' => 10,
        'min_stock' => 2,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($admin)
        ->post('/sales', [
            'payment_method' => 'cash',
            'amount_received' => 7000,
            'sold_at' => now()->toDateTimeString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 4,
                    'unit_price' => 1500,
                ],
            ],
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    expect($product->fresh()->stock)->toBe('6.000');

    $sale = Sale::query()->firstOrFail();
    $item = SaleItem::query()->firstOrFail();

    expect($sale->sale_number)->toBe('S-000001');
    expect($sale->payment_method)->toBe('cash');
    expect((float) $sale->amount_received)->toBe(7000.0);
    expect((float) $sale->change_amount)->toBe(1000.0);
    expect($item->business_id)->toBe($business->id);
});

test('sale fails when stock is insufficient', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $product = Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Azucar',
        'slug' => 'azucar',
        'unit_type' => 'unit',
        'sale_price' => 1000,
        'cost_price' => 700,
        'stock' => 2,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($admin)
        ->from('/sales/create')
        ->post('/sales', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                    'unit_price' => 1000,
                ],
            ],
        ]);

    $response->assertSessionHasErrors('items');
    expect($product->fresh()->stock)->toBe('2.000');
    expect(Sale::query()->count())->toBe(0);
});

test('cash sale fails when amount received is lower than total', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $product = Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Yerba',
        'slug' => 'yerba',
        'unit_type' => 'unit',
        'sale_price' => 2500,
        'cost_price' => 1800,
        'stock' => 5,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($admin)
        ->from('/sales/create')
        ->post('/sales', [
            'payment_method' => 'cash',
            'amount_received' => 1000,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 2500,
                ],
            ],
        ]);

    $response->assertSessionHasErrors('amount_received');
    expect(Sale::query()->count())->toBe(0);
});

test('transfer sale stores payment method without cash amounts', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $product = Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Aceite',
        'slug' => 'aceite',
        'unit_type' => 'unit',
        'sale_price' => 3000,
        'cost_price' => 2200,
        'stock' => 8,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    $this
        ->actingAs($admin)
        ->post('/sales', [
            'payment_method' => 'transfer',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 3000,
                ],
            ],
        ])
        ->assertRedirect();

    $sale = Sale::query()->firstOrFail();

    expect($sale->payment_method)->toBe('transfer');
    expect($sale->amount_received)->toBeNull();
    expect($sale->change_amount)->toBeNull();
});

test('gram-based weighted sale calculates subtotal and stock correctly', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $product = Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Queso',
        'slug' => 'queso',
        'unit_type' => 'weight',
        'weight_unit' => 'g',
        'sale_price' => 1800,
        'cost_price' => 1100,
        'stock' => 1500,
        'min_stock' => 300,
        'is_active' => true,
    ]);

    $this
        ->actingAs($admin)
        ->post('/sales', [
            'payment_method' => 'cash',
            'amount_received' => 5000,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 250,
                    'unit_price' => 1800,
                ],
            ],
        ])
        ->assertRedirect();

    $sale = Sale::query()->firstOrFail();

    expect((float) $sale->subtotal)->toBe(4500.0);
    expect((float) $sale->total)->toBe(4500.0);
    expect((float) $sale->change_amount)->toBe(500.0);
    expect($product->fresh()->stock)->toBe('1250.000');
});

test('sale numbering is sequential per business', function () {
    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();
    $adminA = User::factory()->businessAdmin($businessA->id)->create();
    $adminB = User::factory()->businessAdmin($businessB->id)->create();

    $createProduct = function (Business $business): Product {
        return Product::query()->create([
            'business_id' => $business->id,
            'name' => 'Producto '.$business->id,
            'slug' => 'venta-producto-'.$business->id,
            'unit_type' => 'unit',
            'sale_price' => 100,
            'cost_price' => 50,
            'stock' => 10,
            'min_stock' => 0,
            'is_active' => true,
        ]);
    };

    $productA = $createProduct($businessA);
    $productB = $createProduct($businessB);

    $this->actingAs($adminA)->post('/sales', [
        'items' => [[
            'product_id' => $productA->id,
            'quantity' => 1,
            'unit_price' => 100,
        ]],
    ])->assertRedirect();

    $this->actingAs($adminA)->post('/sales', [
        'items' => [[
            'product_id' => $productA->id,
            'quantity' => 1,
            'unit_price' => 100,
        ]],
    ])->assertRedirect();

    $this->actingAs($adminB)->post('/sales', [
        'items' => [[
            'product_id' => $productB->id,
            'quantity' => 1,
            'unit_price' => 100,
        ]],
    ])->assertRedirect();

    expect(Sale::query()->forBusiness($businessA->id)->orderBy('id')->pluck('sale_number')->all())
        ->toBe(['S-000001', 'S-000002']);
    expect(Sale::query()->forBusiness($businessB->id)->orderBy('id')->pluck('sale_number')->all())
        ->toBe(['S-000001']);
});

test('sale consumes batches using FEFO by nearest expiration date', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $product = Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Ensalada lista',
        'slug' => 'ensalada-lista',
        'unit_type' => 'unit',
        'sale_price' => 2400,
        'cost_price' => 1200,
        'stock' => 10,
        'min_stock' => 0,
        'is_active' => true,
    ]);

    $firstBatch = ProductBatch::query()->create([
        'business_id' => $business->id,
        'product_id' => $product->id,
        'batch_code' => 'B-001',
        'expires_at' => '2026-03-25',
        'quantity' => 4,
        'unit_cost' => 1100,
    ]);

    $secondBatch = ProductBatch::query()->create([
        'business_id' => $business->id,
        'product_id' => $product->id,
        'batch_code' => 'B-002',
        'expires_at' => '2026-03-30',
        'quantity' => 6,
        'unit_cost' => 1150,
    ]);

    $this
        ->actingAs($admin)
        ->post('/sales', [
            'payment_method' => 'cash',
            'amount_received' => 15000,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 5,
                'unit_price' => 2400,
            ]],
        ])
        ->assertRedirect();

    expect($product->fresh()->stock)->toBe('5.000');
    expect($firstBatch->fresh()->quantity)->toBe('0.000');
    expect($secondBatch->fresh()->quantity)->toBe('5.000');
});

test('sale falls back to legacy unbatched stock when tracked batches are not enough', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $product = Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Harina',
        'slug' => 'harina',
        'unit_type' => 'unit',
        'sale_price' => 1800,
        'cost_price' => 1000,
        'stock' => 10,
        'min_stock' => 0,
        'is_active' => true,
    ]);

    $batch = ProductBatch::query()->create([
        'business_id' => $business->id,
        'product_id' => $product->id,
        'batch_code' => 'B-LEGACY',
        'expires_at' => '2026-03-28',
        'quantity' => 3,
        'unit_cost' => 1000,
    ]);

    $this
        ->actingAs($admin)
        ->post('/sales', [
            'payment_method' => 'cash',
            'amount_received' => 10000,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 5,
                'unit_price' => 1800,
            ]],
        ])
        ->assertRedirect();

    expect($product->fresh()->stock)->toBe('5.000');
    expect($batch->fresh()->quantity)->toBe('0.000');
});
