<?php

use App\Models\Business;
use App\Models\Product;
use App\Models\User;

test('products can be filtered by advanced status flags', function () {
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
});

test('advanced product filters stay inside the authenticated business', function () {
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

    $this->actingAs($admin)
        ->get('/products?no_price=1&no_stock=1')
        ->assertOk()
        ->assertSee('Producto filtrado propio')
        ->assertDontSee('Producto filtrado ajeno');
});
