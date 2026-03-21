<?php

use App\Models\Business;
use App\Models\BusinessFeature;
use App\Models\Category;
use App\Models\GlobalProduct;
use App\Models\Product;
use App\Models\User;

test('product catalog lookup prioritizes an existing local product by barcode', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();

    BusinessFeature::query()->create([
        'business_id' => $business->id,
        'feature' => BusinessFeature::GLOBAL_PRODUCT_CATALOG,
        'is_enabled' => true,
    ]);

    Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Yerba local',
        'slug' => 'yerba-local',
        'barcode' => '7790000000010',
        'unit_type' => 'unit',
        'sale_price' => 1200,
        'cost_price' => 800,
        'stock' => 3,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    GlobalProduct::query()->create([
        'name' => 'Yerba global',
        'barcode' => '7790000000010',
        'normalized_name' => 'yerba global',
    ]);

    $this->actingAs($admin)
        ->getJson(route('products.catalog.lookup', ['barcode' => '7790000000010']))
        ->assertOk()
        ->assertJsonPath('local_product.name', 'Yerba local')
        ->assertJsonPath('global_product', null);
});

test('product catalog lookup suggests the local category that matches the global category name', function () {
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
        'name' => 'Bebidas Frias',
        'slug' => 'bebidas-frias-source',
        'is_active' => true,
    ]);

    $targetCategory = Category::query()->create([
        'business_id' => $targetBusiness->id,
        'name' => '  bebidas frias  ',
        'slug' => 'bebidas-frias-target',
        'is_active' => true,
    ]);

    GlobalProduct::query()->create([
        'name' => 'Sprite 2.25L',
        'barcode' => '7791234567890',
        'category_id' => $sourceCategory->id,
        'normalized_name' => 'sprite 2 25l',
    ]);

    $this->actingAs($admin)
        ->getJson(route('products.catalog.lookup', ['barcode' => '7791234567890']))
        ->assertOk()
        ->assertJsonPath('global_product.name', 'Sprite 2.25L')
        ->assertJsonPath('global_product.suggested_category.id', $targetCategory->id);
});

test('product catalog lookup is forbidden when the superadmin did not enable it for the business', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();

    $this->actingAs($admin)
        ->getJson(route('products.catalog.lookup', ['barcode' => '7790000000010']))
        ->assertForbidden();
});
