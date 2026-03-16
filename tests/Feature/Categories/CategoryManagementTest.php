<?php

use App\Models\Business;
use App\Models\Category;
use App\Models\User;

test('business admin can create categories inside their business', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();

    $this->actingAs($admin)
        ->post('/categories', [
            'name' => 'Bebidas',
            'description' => 'Linea de bebidas',
            'is_active' => true,
        ])
        ->assertRedirect(route('categories.index'));

    $category = Category::query()->firstOrFail();

    expect($category->business_id)->toBe($business->id);
    expect($category->name)->toBe('Bebidas');
});

test('business admin can not assign a foreign category to a product', function () {
    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();
    $admin = User::factory()->businessAdmin($businessA->id)->create();

    $foreignCategory = Category::query()->create([
        'business_id' => $businessB->id,
        'name' => 'Ajena',
        'slug' => 'ajena',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)
        ->from('/products/create')
        ->post('/products', [
            'category_id' => $foreignCategory->id,
            'name' => 'Producto propio',
            'slug' => 'producto-propio-categoria',
            'unit_type' => 'unit',
            'sale_price' => 100,
            'cost_price' => 50,
            'stock' => 1,
            'min_stock' => 0,
            'is_active' => true,
        ]);

    $response->assertSessionHasErrors('category_id');
});
