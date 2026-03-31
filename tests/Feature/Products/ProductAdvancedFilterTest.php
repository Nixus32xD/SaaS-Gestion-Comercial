<?php

use App\Models\Business;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\User;

test('products can be filtered by advanced status flags', function () {
    $this->travelTo(now()->create(2026, 3, 31, 10, 0, 0));

    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();

    $createProduct = fn (array $overrides) => Product::query()->create(array_merge([
        'business_id' => $business->id,
        'name' => 'Producto base',
        'slug' => fake()->unique()->slug(),
        'unit_type' => 'unit',
        'sale_price' => 100,
        'cost_price' => 50,
        'stock' => 5,
        'min_stock' => 1,
        'is_active' => true,
    ], $overrides));

    $createProduct([
        'name' => 'Sin precio y sin stock',
        'slug' => 'sin-precio-y-sin-stock',
        'sale_price' => 0,
        'cost_price' => 25,
        'stock' => 0,
        'min_stock' => 2,
    ]);

    $createProduct([
        'name' => 'Sin costo',
        'slug' => 'sin-costo',
        'sale_price' => 250,
        'cost_price' => 0,
        'stock' => 8,
        'min_stock' => 2,
    ]);

    $createProduct([
        'name' => 'Stock bajo',
        'slug' => 'stock-bajo',
        'sale_price' => 300,
        'cost_price' => 120,
        'stock' => 1,
        'min_stock' => 3,
    ]);

    $createProduct([
        'name' => 'Stock saludable',
        'slug' => 'stock-saludable',
        'sale_price' => 400,
        'cost_price' => 150,
        'stock' => 10,
        'min_stock' => 2,
    ]);

    $expiredProduct = $createProduct([
        'name' => 'Lote vencido',
        'slug' => 'lote-vencido',
        'sale_price' => 450,
        'cost_price' => 220,
        'stock' => 4,
        'min_stock' => 1,
        'expiry_alert_days' => 5,
    ]);

    $upcomingProduct = $createProduct([
        'name' => 'Lote por vencer',
        'slug' => 'lote-por-vencer',
        'sale_price' => 480,
        'cost_price' => 230,
        'stock' => 6,
        'min_stock' => 1,
        'expiry_alert_days' => 5,
    ]);

    $validProduct = $createProduct([
        'name' => 'Lote vigente',
        'slug' => 'lote-vigente',
        'sale_price' => 520,
        'cost_price' => 250,
        'stock' => 7,
        'min_stock' => 1,
        'expiry_alert_days' => 5,
    ]);

    $noExpirationProduct = $createProduct([
        'name' => 'Lote sin vencimiento',
        'slug' => 'lote-sin-vencimiento',
        'sale_price' => 510,
        'cost_price' => 245,
        'stock' => 3,
        'min_stock' => 1,
        'expiry_alert_days' => 5,
    ]);

    ProductBatch::query()->create([
        'business_id' => $business->id,
        'product_id' => $expiredProduct->id,
        'batch_code' => 'EXP-1',
        'expires_at' => '2026-03-30',
        'quantity' => 4,
        'unit_cost' => 210,
    ]);

    ProductBatch::query()->create([
        'business_id' => $business->id,
        'product_id' => $upcomingProduct->id,
        'batch_code' => 'UP-1',
        'expires_at' => '2026-04-03',
        'quantity' => 6,
        'unit_cost' => 220,
    ]);

    ProductBatch::query()->create([
        'business_id' => $business->id,
        'product_id' => $validProduct->id,
        'batch_code' => 'VAL-1',
        'expires_at' => '2026-04-20',
        'quantity' => 7,
        'unit_cost' => 230,
    ]);

    ProductBatch::query()->create([
        'business_id' => $business->id,
        'product_id' => $noExpirationProduct->id,
        'batch_code' => 'SV-1',
        'expires_at' => null,
        'quantity' => 3,
        'unit_cost' => 240,
    ]);

    $this->actingAs($admin)
        ->get('/products?no_price=1&no_stock=1')
        ->assertOk()
        ->assertSee('Sin precio y sin stock')
        ->assertDontSee('Sin costo')
        ->assertDontSee('Stock bajo')
        ->assertDontSee('Stock saludable');

    $this->actingAs($admin)
        ->get('/products?no_cost=1')
        ->assertOk()
        ->assertSee('Sin costo')
        ->assertDontSee('Stock saludable');

    $this->actingAs($admin)
        ->get('/products?with_stock=1&low_stock=1')
        ->assertOk()
        ->assertSee('Stock bajo')
        ->assertDontSee('Sin precio y sin stock')
        ->assertDontSee('Stock saludable');

    $this->actingAs($admin)
        ->get('/products?expired_batches=1')
        ->assertOk()
        ->assertSee('Lote vencido')
        ->assertDontSee('Lote por vencer')
        ->assertDontSee('Lote vigente')
        ->assertDontSee('Lote sin vencimiento');

    $this->actingAs($admin)
        ->get('/products?expired_batches=1&upcoming_batches=1')
        ->assertOk()
        ->assertSee('Lote vencido')
        ->assertSee('Lote por vencer')
        ->assertDontSee('Lote vigente')
        ->assertDontSee('Lote sin vencimiento');

    $this->actingAs($admin)
        ->get('/products?valid_batches=1&no_expiration_batches=1')
        ->assertOk()
        ->assertSee('Lote vigente')
        ->assertSee('Lote sin vencimiento')
        ->assertDontSee('Lote vencido')
        ->assertDontSee('Lote por vencer');

    $this->travelBack();
});

test('advanced product filters stay inside the authenticated business', function () {
    $this->travelTo(now()->create(2026, 3, 31, 10, 0, 0));

    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();
    $admin = User::factory()->businessAdmin($businessA->id)->create();

    Product::query()->create([
        'business_id' => $businessA->id,
        'name' => 'Producto filtrado propio',
        'slug' => 'producto-filtrado-propio',
        'unit_type' => 'unit',
        'sale_price' => 0,
        'cost_price' => 10,
        'stock' => 0,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    Product::query()->create([
        'business_id' => $businessB->id,
        'name' => 'Producto filtrado ajeno',
        'slug' => 'producto-filtrado-ajeno',
        'unit_type' => 'unit',
        'sale_price' => 0,
        'cost_price' => 10,
        'stock' => 0,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    $ownExpiredProduct = Product::query()->create([
        'business_id' => $businessA->id,
        'name' => 'Producto vencido propio',
        'slug' => 'producto-vencido-propio',
        'unit_type' => 'unit',
        'sale_price' => 120,
        'cost_price' => 40,
        'stock' => 2,
        'min_stock' => 1,
        'expiry_alert_days' => 3,
        'is_active' => true,
    ]);

    $foreignExpiredProduct = Product::query()->create([
        'business_id' => $businessB->id,
        'name' => 'Producto vencido ajeno',
        'slug' => 'producto-vencido-ajeno',
        'unit_type' => 'unit',
        'sale_price' => 120,
        'cost_price' => 40,
        'stock' => 2,
        'min_stock' => 1,
        'expiry_alert_days' => 3,
        'is_active' => true,
    ]);

    ProductBatch::query()->create([
        'business_id' => $businessA->id,
        'product_id' => $ownExpiredProduct->id,
        'batch_code' => 'OWN-EXP',
        'expires_at' => '2026-03-30',
        'quantity' => 2,
        'unit_cost' => 40,
    ]);

    ProductBatch::query()->create([
        'business_id' => $businessB->id,
        'product_id' => $foreignExpiredProduct->id,
        'batch_code' => 'FOR-EXP',
        'expires_at' => '2026-03-30',
        'quantity' => 2,
        'unit_cost' => 40,
    ]);

    $this->actingAs($admin)
        ->get('/products?no_price=1&no_stock=1')
        ->assertOk()
        ->assertSee('Producto filtrado propio')
        ->assertDontSee('Producto filtrado ajeno');

    $this->actingAs($admin)
        ->get('/products?expired_batches=1')
        ->assertOk()
        ->assertSee('Producto vencido propio')
        ->assertDontSee('Producto vencido ajeno');

    $this->travelBack();
});
