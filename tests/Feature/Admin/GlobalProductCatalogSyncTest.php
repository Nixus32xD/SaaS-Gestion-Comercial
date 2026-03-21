<?php

use App\Models\Business;
use App\Models\Category;
use App\Models\GlobalProduct;
use App\Models\Product;
use App\Models\User;

test('superadmin can sync local products into the global catalog without duplicating matches', function () {
    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();
    $superAdmin = User::factory()->superadmin()->create();

    $sourceCategory = Category::query()->create([
        'business_id' => $businessA->id,
        'name' => 'Bebidas',
        'slug' => 'bebidas-a',
        'is_active' => true,
    ]);

    $targetCategory = Category::query()->create([
        'business_id' => $businessB->id,
        'name' => 'Bebidas',
        'slug' => 'bebidas-b',
        'is_active' => true,
    ]);

    $productA = Product::query()->create([
        'business_id' => $businessA->id,
        'category_id' => $sourceCategory->id,
        'name' => 'Coca Cola 500ml',
        'slug' => 'coca-cola-500ml-a',
        'barcode' => '7790895000011',
        'unit_type' => 'unit',
        'sale_price' => 1500,
        'cost_price' => 1000,
        'stock' => 10,
        'min_stock' => 2,
        'is_active' => true,
    ]);

    $productB = Product::query()->create([
        'business_id' => $businessB->id,
        'category_id' => $targetCategory->id,
        'name' => 'Coca Cola 500ml',
        'slug' => 'coca-cola-500ml-b',
        'barcode' => '7790895000011',
        'unit_type' => 'unit',
        'sale_price' => 1600,
        'cost_price' => 1100,
        'stock' => 4,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($superAdmin)
        ->post(route('admin.global-products.sync'))
        ->assertRedirect(route('admin.global-products.index'))
        ->assertSessionHas('global_catalog_sync_summary', function (array $summary): bool {
            return $summary['analyzed'] === 2
                && $summary['created'] === 1
                && $summary['existing'] === 1
                && $summary['skipped'] === 0
                && $summary['linked'] === 2
                && $summary['conflicts'] === 0
                && $summary['error_count'] === 0;
        });

    expect(GlobalProduct::query()->count())->toBe(1);

    $globalProduct = GlobalProduct::query()->firstOrFail();

    expect($globalProduct->category_id)->toBe($sourceCategory->id);
    expect($productA->fresh()->global_product_id)->toBe($globalProduct->id);
    expect($productB->fresh()->global_product_id)->toBe($globalProduct->id);
});

test('sync skips local products without barcode and sku', function () {
    $business = Business::factory()->create();
    $superAdmin = User::factory()->superadmin()->create();

    $product = Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Producto sin identificador',
        'slug' => 'producto-sin-identificador',
        'unit_type' => 'unit',
        'sale_price' => 1200,
        'cost_price' => 800,
        'stock' => 10,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($superAdmin)
        ->post(route('admin.global-products.sync'))
        ->assertRedirect(route('admin.global-products.index'))
        ->assertSessionHas('global_catalog_sync_summary', function (array $summary): bool {
            return $summary['analyzed'] === 1
                && $summary['created'] === 0
                && $summary['existing'] === 0
                && $summary['skipped'] === 1
                && $summary['linked'] === 0
                && $summary['conflicts'] === 0
                && $summary['error_count'] === 0;
        });

    expect(GlobalProduct::query()->count())->toBe(0);
    expect($product->fresh()->global_product_id)->toBeNull();
});
