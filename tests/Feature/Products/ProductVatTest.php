<?php

use App\Models\Business;
use App\Models\Product;
use App\Models\User;

test('business admin can store product vat configuration', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();

    $this
        ->actingAs($admin)
        ->post(route('products.store'), [
            'name' => 'Leche larga vida',
            'unit_type' => 'unit',
            'sale_price' => 1500,
            'cost_price' => 1000,
            'vat_treatment' => 'gravado',
            'vat_rate' => '10.5',
            'stock' => 12,
            'min_stock' => 2,
            'is_active' => true,
        ])
        ->assertRedirect(route('products.index'));

    $product = Product::query()->firstOrFail();

    expect($product->vat_treatment)->toBe('gravado');
    expect((float) $product->vat_rate)->toBe(10.5);
});
