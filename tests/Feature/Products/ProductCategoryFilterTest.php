<?php

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;

test('products can be filtered by category inside the business', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();

    $bebidas = Category::query()->create([
        'business_id' => $business->id,
        'name' => 'Bebidas',
        'slug' => 'bebidas',
        'is_active' => true,
    ]);

    $almacen = Category::query()->create([
        'business_id' => $business->id,
        'name' => 'Almacen',
        'slug' => 'almacen',
        'is_active' => true,
    ]);

    Product::query()->create([
        'business_id' => $business->id,
        'category_id' => $bebidas->id,
        'name' => 'Coca Cola',
        'slug' => 'coca-cola',
        'unit_type' => 'unit',
        'sale_price' => 100,
        'cost_price' => 50,
        'stock' => 3,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    Product::query()->create([
        'business_id' => $business->id,
        'category_id' => $almacen->id,
        'name' => 'Yerba',
        'slug' => 'yerba',
        'unit_type' => 'unit',
        'sale_price' => 100,
        'cost_price' => 50,
        'stock' => 3,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->get("/products?category_id={$bebidas->id}")
        ->assertOk()
        ->assertSee('Coca Cola')
        ->assertDontSee('Yerba');
});
