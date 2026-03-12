<?php

use App\Models\Business;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;

test('business lists only include records from the authenticated business', function () {
    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();

    $admin = User::factory()->businessAdmin($businessA->id)->create();

    Product::query()->create([
        'business_id' => $businessA->id,
        'name' => 'Producto propio',
        'slug' => 'producto-propio',
        'unit_type' => 'unit',
        'sale_price' => 100,
        'cost_price' => 50,
        'stock' => 10,
        'min_stock' => 2,
        'is_active' => true,
    ]);

    Product::query()->create([
        'business_id' => $businessB->id,
        'name' => 'Producto ajeno',
        'slug' => 'producto-ajeno',
        'unit_type' => 'unit',
        'sale_price' => 100,
        'cost_price' => 50,
        'stock' => 10,
        'min_stock' => 2,
        'is_active' => true,
    ]);

    Supplier::query()->create([
        'business_id' => $businessA->id,
        'name' => 'Proveedor propio',
    ]);

    Supplier::query()->create([
        'business_id' => $businessB->id,
        'name' => 'Proveedor ajeno',
    ]);

    $this->actingAs($admin)->get('/products')
        ->assertOk()
        ->assertSee('Producto propio')
        ->assertDontSee('Producto ajeno');

    $this->actingAs($admin)->get('/suppliers')
        ->assertOk()
        ->assertSee('Proveedor propio')
        ->assertDontSee('Proveedor ajeno');
});

test('admins can not access detail routes from another business', function () {
    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();

    $admin = User::factory()->businessAdmin($businessA->id)->create();
    $foreignAdmin = User::factory()->businessAdmin($businessB->id)->create();

    $product = Product::query()->create([
        'business_id' => $businessB->id,
        'name' => 'Producto B',
        'slug' => 'producto-b',
        'unit_type' => 'unit',
        'sale_price' => 200,
        'cost_price' => 100,
        'stock' => 10,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    $supplier = Supplier::query()->create([
        'business_id' => $businessB->id,
        'name' => 'Proveedor B',
    ]);

    $sale = Sale::query()->create([
        'business_id' => $businessB->id,
        'user_id' => $foreignAdmin->id,
        'sale_number' => 'S-000001',
        'subtotal' => 100,
        'discount' => 0,
        'total' => 100,
        'sold_at' => now(),
    ]);

    $purchase = Purchase::query()->create([
        'business_id' => $businessB->id,
        'user_id' => $foreignAdmin->id,
        'supplier_id' => $supplier->id,
        'purchase_number' => 'P-000001',
        'subtotal' => 100,
        'total' => 100,
        'purchased_at' => now(),
    ]);

    $this->actingAs($admin)->get("/products/{$product->id}/edit")->assertForbidden();
    $this->actingAs($admin)->get("/suppliers/{$supplier->id}/edit")->assertForbidden();
    $this->actingAs($admin)->get("/sales/{$sale->id}")->assertForbidden();
    $this->actingAs($admin)->get("/purchases/{$purchase->id}")->assertForbidden();
});
