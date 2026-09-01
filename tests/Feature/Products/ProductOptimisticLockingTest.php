<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\Product;
use App\Models\User;
use App\Services\BranchProductStockService;

test('product update rejects stale catalog version and ignores manipulated inventory fields', function () {
    $business = Business::factory()->create();
    $branch = $business->defaultBranch;
    $user = User::factory()->businessAdmin($business->id)->create();
    $product = optimisticProduct($business, 8);
    $branchStock = $product->branchStocks()->where('branch_id', $branch->id)->firstOrFail();
    $payload = optimisticPayload($product, $branchStock);

    $this->actingAs($user)->withSession(['business_id' => $business->id, 'branch_id' => $branch->id])
        ->put(route('products.update', $product), [...$payload, 'sale_price' => 200, 'stock' => 999, 'batch_code' => 'MANIPULADO'])
        ->assertRedirect(route('products.index'));

    $product->refresh();
    expect((float) $product->sale_price)->toBe(200.0)
        ->and($product->edit_version)->toBe(2)
        ->and((float) $branchStock->fresh()->stock)->toBe(8.0);

    $this->actingAs($user)->withSession(['business_id' => $business->id, 'branch_id' => $branch->id])
        ->from(route('products.edit', $product))
        ->put(route('products.update', $product), [...$payload, 'sale_price' => 150])
        ->assertRedirect(route('products.edit', $product))
        ->assertSessionHasErrors('edit_version');

    expect((float) $product->fresh()->sale_price)->toBe(200.0);
});

test('stock operations do not increment catalog or branch configuration edit versions', function () {
    $business = Business::factory()->create();
    $branch = $business->defaultBranch;
    $product = optimisticProduct($business, 2);
    $stock = $product->branchStocks()->where('branch_id', $branch->id)->firstOrFail();

    app(BranchProductStockService::class)->adjust($branch, $product, 3);
    app(BranchProductStockService::class)->reserve($branch, $product, 1);

    expect($product->fresh()->edit_version)->toBe(1)
        ->and($stock->fresh()->edit_version)->toBe(1);
});

test('minimum stock uses a version per branch without false conflicts in another branch', function () {
    $business = Business::factory()->create();
    $branchA = $business->defaultBranch;
    $branchB = optimisticBranch($business, 'centro');
    $user = User::factory()->businessAdmin($business->id)->create();
    $product = optimisticProduct($business, 5);
    $service = app(BranchProductStockService::class);
    $service->adjust($branchB, $product, 4);
    $stockA = $product->branchStocks()->where('branch_id', $branchA->id)->firstOrFail();
    $stockB = $product->branchStocks()->where('branch_id', $branchB->id)->firstOrFail();
    $payloadA = optimisticPayload($product, $stockA);
    $payloadB = optimisticPayload($product, $stockB);

    $this->actingAs($user)->withSession(['business_id' => $business->id, 'branch_id' => $branchA->id])
        ->put(route('products.update', $product), [...$payloadA, 'min_stock' => 3])
        ->assertRedirect();

    $this->actingAs($user)->withSession(['business_id' => $business->id, 'branch_id' => $branchA->id])
        ->from(route('products.edit', $product))
        ->put(route('products.update', $product), [...$payloadA, 'min_stock' => 2])
        ->assertSessionHasErrors('branch_stock_edit_version');

    $this->actingAs($user)->withSession(['business_id' => $business->id, 'branch_id' => $branchB->id])
        ->put(route('products.update', $product), [...$payloadB, 'sale_price' => 150])
        ->assertRedirect();

    expect((float) $stockA->fresh()->min_stock)->toBe(3.0)
        ->and((float) $stockB->fresh()->min_stock)->toBe(0.0)
        ->and((float) $product->fresh()->sale_price)->toBe(150.0);
});

test('measurement cannot change after inventory exists', function () {
    $business = Business::factory()->create();
    $branch = $business->defaultBranch;
    $user = User::factory()->businessAdmin($business->id)->create();
    $product = optimisticProduct($business, 1);
    $stock = $product->branchStocks()->where('branch_id', $branch->id)->firstOrFail();

    $this->actingAs($user)->withSession(['business_id' => $business->id, 'branch_id' => $branch->id])
        ->from(route('products.edit', $product))
        ->put(route('products.update', $product), [...optimisticPayload($product, $stock), 'unit_type' => 'weight', 'weight_unit' => 'kg'])
        ->assertSessionHasErrors('unit_type');

    expect($product->fresh()->unit_type)->toBe('unit');
});

function optimisticProduct(Business $business, float $stock): Product
{
    $product = Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Producto bloqueo '.fake()->unique()->word(),
        'slug' => fake()->unique()->slug(),
        'unit_type' => 'unit',
        'sale_price' => 100,
        'cost_price' => 50,
        'stock' => $stock,
        'reserved_stock' => 0,
        'min_stock' => 0,
        'vat_treatment' => 'gravado',
        'vat_rate' => 21,
        'is_active' => true,
    ]);

    return $product->fresh();
}

function optimisticBranch(Business $business, string $code): Branch
{
    return Branch::query()->create(['business_id' => $business->id, 'name' => 'Sucursal '.ucfirst($code), 'code' => $code, 'is_active' => true, 'is_default' => false]);
}

function optimisticPayload(Product $product, $branchStock): array
{
    return [
        'category_id' => $product->category_id,
        'supplier_id' => $product->supplier_id,
        'name' => $product->name,
        'slug' => $product->slug,
        'description' => $product->description,
        'barcode' => $product->barcode,
        'sku' => $product->sku,
        'unit_type' => $product->unit_type,
        'weight_unit' => $product->weight_unit,
        'sale_price' => (float) $product->sale_price,
        'cost_price' => (float) $product->cost_price,
        'vat_treatment' => $product->vat_treatment,
        'vat_rate' => (float) $product->vat_rate,
        'min_stock' => (float) $branchStock->min_stock,
        'shelf_life_days' => $product->shelf_life_days,
        'expiry_alert_days' => $product->expiry_alert_days ?: 15,
        'is_active' => true,
        'edit_version' => $product->edit_version,
        'branch_stock_edit_version' => $branchStock->edit_version,
    ];
}
