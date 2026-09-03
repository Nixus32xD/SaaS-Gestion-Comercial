<?php

use App\Models\Branch;
use App\Models\BranchProductStock;
use App\Models\Business;
use App\Models\Product;
use App\Services\LowStockAlertService;

test('low stock alert service only returns active low stock products from the same business', function () {
    $business = Business::factory()->create();
    $otherBusiness = Business::factory()->create();

    Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Producto critico',
        'slug' => 'producto-critico',
        'unit_type' => 'unit',
        'sale_price' => 100,
        'cost_price' => 70,
        'stock' => 0,
        'min_stock' => 3,
        'is_active' => true,
    ]);

    Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Producto estable',
        'slug' => 'producto-estable',
        'unit_type' => 'unit',
        'sale_price' => 100,
        'cost_price' => 70,
        'stock' => 10,
        'min_stock' => 3,
        'is_active' => true,
    ]);

    Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Producto sin alerta',
        'slug' => 'producto-sin-alerta',
        'unit_type' => 'unit',
        'sale_price' => 100,
        'cost_price' => 70,
        'stock' => 0,
        'min_stock' => 0,
        'is_active' => true,
    ]);

    Product::query()->create([
        'business_id' => $otherBusiness->id,
        'name' => 'Producto ajeno',
        'slug' => 'producto-ajeno',
        'unit_type' => 'unit',
        'sale_price' => 100,
        'cost_price' => 70,
        'stock' => 0,
        'min_stock' => 3,
        'is_active' => true,
    ]);

    /** @var LowStockAlertService $service */
    $service = app(LowStockAlertService::class);
    $items = $service->listForBusiness($business->id);
    $summary = $service->summarizeForBusiness($business->id);

    expect($items)->toHaveCount(1);
    expect($items->first()['product_name'])->toBe('Producto critico');
    expect($items->first()['status'])->toBe('out_of_stock');
    expect($summary)->toBe([
        'total' => 1,
        'out_of_stock' => 1,
        'low_stock' => 0,
    ]);
});

test('low stock alerts use available stock and minimums per active branch without leaking businesses', function () {
    $business = Business::factory()->create();
    $branchA = $business->defaultBranch;
    $branchB = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Sucursal B',
        'code' => 'sucursal-b',
        'is_active' => true,
        'is_default' => false,
    ]);
    $inactiveBranch = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Sucursal cerrada',
        'code' => 'sucursal-cerrada',
        'is_active' => false,
        'is_default' => false,
    ]);
    $product = lowStockProduct($business, 'Producto compartido');
    $inactiveProduct = lowStockProduct($business, 'Producto inactivo', false);
    $otherBusiness = Business::factory()->create();
    $otherProduct = lowStockProduct($otherBusiness, 'Producto compartido');

    lowStockForBranch($branchA, $product, 4, 3, 2);
    lowStockForBranch($branchB, $product, 10, 0, 7);
    lowStockForBranch($inactiveBranch, $product, 0, 0, 5);
    lowStockForBranch($branchA, $inactiveProduct, 0, 0, 5);
    lowStockForBranch($otherBusiness->defaultBranch, $otherProduct, 0, 0, 5);

    $service = app(LowStockAlertService::class);
    $items = $service->listForBusiness($business->id);
    $summary = $service->summarizeForBusiness($business->id);

    expect($items)->toHaveCount(1)
        ->and($items->first())->toMatchArray([
            'branch_id' => $branchA->id,
            'branch_name' => $branchA->name,
            'product_id' => $product->id,
            'stock' => 4.0,
            'reserved_stock' => 3.0,
            'available_stock' => 1.0,
            'min_stock' => 2.0,
            'status' => 'low_stock',
        ])
        ->and($summary)->toBe(['total' => 1, 'out_of_stock' => 0, 'low_stock' => 1]);

    lowStockForBranch($branchA, $product, 3, 3, 2);

    expect($service->listForBusiness($business->id)->first())->toMatchArray([
        'branch_id' => $branchA->id,
        'available_stock' => 0.0,
        'status' => 'out_of_stock',
    ]);
});

function lowStockProduct(Business $business, string $name, bool $active = true): Product
{
    return Product::query()->create([
        'business_id' => $business->id,
        'name' => $name,
        'slug' => str($name)->slug()->append('-'.fake()->unique()->numberBetween(1, 999999)),
        'unit_type' => 'unit',
        'sale_price' => 100,
        'cost_price' => 70,
        'stock' => 0,
        'reserved_stock' => 0,
        'min_stock' => 0,
        'is_active' => $active,
    ]);
}

function lowStockForBranch(Branch $branch, Product $product, float $stock, float $reservedStock, float $minStock): void
{
    BranchProductStock::query()->updateOrCreate([
        'business_id' => $product->business_id,
        'branch_id' => $branch->id,
        'product_id' => $product->id,
    ], [
        'stock' => $stock,
        'reserved_stock' => $reservedStock,
        'min_stock' => $minStock,
    ]);
}
