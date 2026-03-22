<?php

use App\Models\Business;
use App\Models\BusinessFeature;
use App\Models\Category;
use App\Models\GlobalProduct;
use App\Models\Product;
use App\Models\ProductBatch;
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

test('purchase with new product rejects duplicated sku from the same business', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();

    Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Producto existente',
        'slug' => 'producto-existente',
        'sku' => 'SKU-001',
        'unit_type' => 'unit',
        'sale_price' => 100,
        'cost_price' => 50,
        'stock' => 3,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($admin)
        ->from('/purchases/create')
        ->post('/purchases', [
            'items' => [
                [
                    'product_id' => null,
                    'quantity' => 1,
                    'unit_cost' => 90,
                    'product' => [
                        'name' => 'Nuevo producto',
                        'sku' => 'SKU-001',
                        'unit_type' => 'unit',
                        'sale_price' => 130,
                    ],
                ],
            ],
        ]);

    $response->assertSessionHasErrors('items.0.product.sku');
    expect(Purchase::query()->count())->toBe(0);
});

test('purchase can create a new local product linked to the global catalog', function () {
    $sourceBusiness = Business::factory()->create();
    $targetBusiness = Business::factory()->create();
    $admin = User::factory()->businessAdmin($targetBusiness->id)->create();

    BusinessFeature::query()->create([
        'business_id' => $targetBusiness->id,
        'feature' => BusinessFeature::GLOBAL_PRODUCT_CATALOG,
        'is_enabled' => true,
    ]);

    $sourceCategory = Category::query()->create([
        'business_id' => $sourceBusiness->id,
        'name' => 'Lacteos',
        'slug' => 'lacteos-source',
        'is_active' => true,
    ]);

    $targetCategory = Category::query()->create([
        'business_id' => $targetBusiness->id,
        'name' => 'lacteos',
        'slug' => 'lacteos-target',
        'is_active' => true,
    ]);

    $globalProduct = GlobalProduct::query()->create([
        'name' => 'Leche Entera 1L',
        'barcode' => '7792222222222',
        'category_id' => $sourceCategory->id,
        'normalized_name' => 'leche entera 1l',
    ]);

    $this->actingAs($admin)
        ->post('/purchases', [
            'items' => [[
                'product_id' => null,
                'quantity' => 2,
                'unit_cost' => 900,
                'product' => [
                    'global_product_id' => $globalProduct->id,
                    'name' => 'Leche Entera 1L',
                    'barcode' => '7792222222222',
                    'unit_type' => 'unit',
                    'sale_price' => 1300,
                ],
            ]],
        ])
        ->assertRedirect();

    $product = Product::query()->firstOrFail();

    expect($product->global_product_id)->toBe($globalProduct->id);
    expect($product->category_id)->toBe($targetCategory->id);
    expect($product->stock)->toBe('2.000');
});

test('purchase creates a tracked batch with automatic code when none is provided', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $product = Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Leche',
        'slug' => 'leche',
        'unit_type' => 'unit',
        'sale_price' => 1500,
        'cost_price' => 900,
        'stock' => 1,
        'min_stock' => 0,
        'expiry_alert_days' => 15,
        'is_active' => true,
    ]);

    $this
        ->actingAs($admin)
        ->post('/purchases', [
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 3,
                'unit_cost' => 950,
                'expires_at' => '2026-04-15',
            ]],
        ])
        ->assertRedirect();

    $batch = ProductBatch::query()->firstOrFail();

    expect($product->fresh()->stock)->toBe('4.000');
    expect($batch->product_id)->toBe($product->id);
    expect($batch->batch_code)->toStartWith('L-'.now()->format('Y').'-');
    expect($batch->expires_at?->toDateString())->toBe('2026-04-15');
    expect($batch->quantity)->toBe('3.000');
});

test('purchase can add quantity into an existing batch code', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $product = Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Yogur',
        'slug' => 'yogur',
        'unit_type' => 'unit',
        'sale_price' => 1100,
        'cost_price' => 700,
        'stock' => 2,
        'min_stock' => 0,
        'expiry_alert_days' => 15,
        'is_active' => true,
    ]);

    $batch = ProductBatch::query()->create([
        'business_id' => $business->id,
        'product_id' => $product->id,
        'batch_code' => 'LOT-BASE',
        'expires_at' => '2026-04-20',
        'quantity' => 1,
        'unit_cost' => 700,
    ]);

    $this
        ->actingAs($admin)
        ->post('/purchases', [
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_cost' => 720,
                'batch_code' => 'LOT-BASE',
                'expires_at' => '2026-04-20',
            ]],
        ])
        ->assertRedirect();

    expect($product->fresh()->stock)->toBe('4.000');
    expect($batch->fresh()->quantity)->toBe('3.000');
    expect($batch->fresh()->unit_cost)->toBe('720.00');
    expect(ProductBatch::query()->count())->toBe(1);
});
