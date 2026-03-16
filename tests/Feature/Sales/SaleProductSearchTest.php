<?php

use App\Models\Business;
use App\Models\Product;
use App\Models\User;

test('sales product search returns matching products beyond the initial create payload', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();

    foreach (range(1, 350) as $index) {
        Product::query()->create([
            'business_id' => $business->id,
            'name' => sprintf('Producto %03d', $index),
            'slug' => sprintf('producto-%03d', $index),
            'sku' => sprintf('SKU-%03d', $index),
            'unit_type' => 'unit',
            'sale_price' => 100 + $index,
            'cost_price' => 50 + $index,
            'stock' => 5,
            'min_stock' => 1,
            'is_active' => true,
        ]);
    }

    $this->actingAs($admin)
        ->getJson('/sales/products/search?search=Producto 350')
        ->assertOk()
        ->assertJsonPath('products.0.name', 'Producto 350');
});

test('sales product search stays isolated by business', function () {
    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();
    $admin = User::factory()->businessAdmin($businessA->id)->create();

    Product::query()->create([
        'business_id' => $businessA->id,
        'name' => 'Yerba propia',
        'slug' => 'yerba-propia',
        'sku' => 'A-001',
        'unit_type' => 'unit',
        'sale_price' => 100,
        'cost_price' => 50,
        'stock' => 5,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    Product::query()->create([
        'business_id' => $businessB->id,
        'name' => 'Yerba ajena',
        'slug' => 'yerba-ajena',
        'sku' => 'B-001',
        'unit_type' => 'unit',
        'sale_price' => 100,
        'cost_price' => 50,
        'stock' => 5,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->getJson('/sales/products/search?search=Yerba')
        ->assertOk()
        ->assertJsonFragment(['name' => 'Yerba propia'])
        ->assertJsonMissing(['name' => 'Yerba ajena']);
});
