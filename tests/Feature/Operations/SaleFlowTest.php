<?php

use App\Models\Business;
use App\Models\Product;
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
