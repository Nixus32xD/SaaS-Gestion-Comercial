<?php

use App\Models\Business;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\User;

test('purchase increments stock updates cost and stores business on items', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $supplier = Supplier::query()->create([
        'business_id' => $business->id,
        'name' => 'Proveedor Uno',
    ]);
    $product = Product::query()->create([
        'business_id' => $business->id,
        'supplier_id' => $supplier->id,
        'name' => 'Yerba',
        'slug' => 'yerba',
        'unit_type' => 'unit',
        'sale_price' => 1200,
        'cost_price' => 800,
        'stock' => 4,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($admin)
        ->post('/purchases', [
            'supplier_id' => $supplier->id,
            'purchased_at' => now()->toDateTimeString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 3,
                    'unit_cost' => 950,
                ],
            ],
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    expect($product->fresh()->stock)->toBe('7.000');
    expect($product->fresh()->cost_price)->toBe('950.00');

    $purchase = Purchase::query()->firstOrFail();
    $item = PurchaseItem::query()->firstOrFail();

    expect($purchase->purchase_number)->toBe('P-000001');
    expect($item->business_id)->toBe($business->id);
});

test('purchase numbering is sequential per business', function () {
    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();
    $adminA = User::factory()->businessAdmin($businessA->id)->create();
    $adminB = User::factory()->businessAdmin($businessB->id)->create();

    $createProduct = function (Business $business): Product {
        return Product::query()->create([
            'business_id' => $business->id,
            'name' => 'Producto '.$business->id,
            'slug' => 'producto-'.$business->id,
            'unit_type' => 'unit',
            'sale_price' => 100,
            'cost_price' => 50,
            'stock' => 1,
            'min_stock' => 0,
            'is_active' => true,
        ]);
    };

    $productA = $createProduct($businessA);
    $productB = $createProduct($businessB);

    $this->actingAs($adminA)->post('/purchases', [
        'items' => [[
            'product_id' => $productA->id,
            'quantity' => 1,
            'unit_cost' => 60,
        ]],
    ])->assertRedirect();

    $this->actingAs($adminA)->post('/purchases', [
        'items' => [[
            'product_id' => $productA->id,
            'quantity' => 1,
            'unit_cost' => 70,
        ]],
    ])->assertRedirect();

    $this->actingAs($adminB)->post('/purchases', [
        'items' => [[
            'product_id' => $productB->id,
            'quantity' => 1,
            'unit_cost' => 80,
        ]],
    ])->assertRedirect();

    expect(Purchase::query()->forBusiness($businessA->id)->orderBy('id')->pluck('purchase_number')->all())
        ->toBe(['P-000001', 'P-000002']);
    expect(Purchase::query()->forBusiness($businessB->id)->orderBy('id')->pluck('purchase_number')->all())
        ->toBe(['P-000001']);
});

test('gram-based weighted purchase calculates subtotal and stock correctly', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $supplier = Supplier::query()->create([
        'business_id' => $business->id,
        'name' => 'Proveedor gramos',
    ]);
    $product = Product::query()->create([
        'business_id' => $business->id,
        'supplier_id' => $supplier->id,
        'name' => 'Jamon',
        'slug' => 'jamon',
        'unit_type' => 'weight',
        'weight_unit' => 'g',
        'sale_price' => 2200,
        'cost_price' => 1200,
        'stock' => 500,
        'min_stock' => 100,
        'is_active' => true,
    ]);

    $this
        ->actingAs($admin)
        ->post('/purchases', [
            'supplier_id' => $supplier->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1000,
                    'unit_cost' => 1200,
                ],
            ],
        ])
        ->assertRedirect();

    $purchase = Purchase::query()->firstOrFail();

    expect((float) $purchase->subtotal)->toBe(12000.0);
    expect((float) $purchase->total)->toBe(12000.0);
    expect($product->fresh()->stock)->toBe('1500.000');
});
