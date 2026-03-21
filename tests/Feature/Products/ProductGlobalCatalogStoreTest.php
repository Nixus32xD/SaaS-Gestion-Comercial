<?php

use App\Models\Business;
use App\Models\BusinessFeature;
use App\Models\Category;
use App\Models\GlobalProduct;
use App\Models\Product;
use App\Models\User;

test('business admin can create a local product linked to the global catalog and reuse a matching category', function () {
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
        'name' => 'Gaseosas',
        'slug' => 'gaseosas-source',
        'is_active' => true,
    ]);

    $targetCategory = Category::query()->create([
        'business_id' => $targetBusiness->id,
        'name' => 'gaseosas',
        'slug' => 'gaseosas-target',
        'is_active' => true,
    ]);

    $globalProduct = GlobalProduct::query()->create([
        'name' => 'Pepsi 500ml',
        'barcode' => '7791111111111',
        'category_id' => $sourceCategory->id,
        'normalized_name' => 'pepsi 500ml',
    ]);

    $this->actingAs($admin)
        ->post(route('products.store'), [
            'global_product_id' => $globalProduct->id,
            'name' => 'Pepsi 500ml',
            'barcode' => '7791111111111',
            'unit_type' => 'unit',
            'sale_price' => 1800,
            'cost_price' => 1200,
            'stock' => 2,
            'min_stock' => 1,
            'is_active' => true,
        ])
        ->assertRedirect(route('products.index'));

    $product = Product::query()->firstOrFail();

    expect($product->global_product_id)->toBe($globalProduct->id);
    expect($product->category_id)->toBe($targetCategory->id);
    expect($product->sale_price)->toBe('1800.00');
    expect($product->cost_price)->toBe('1200.00');
});
