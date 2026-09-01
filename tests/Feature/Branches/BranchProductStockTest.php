<?php

use App\Models\Branch;
use App\Models\BranchProductStock;
use App\Models\Business;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\BranchProductStockService;
use App\Services\SaleService;

test('stock and reservations are independent for each branch', function () {
    $business = Business::factory()->create();
    $defaultBranch = $business->defaultBranch;
    $branch = createStockBranch($business, 'centro');
    $product = createStockProduct($business, 10);
    $stockService = app(BranchProductStockService::class);

    $stockService->adjust($branch, $product, 20, 3);
    $stockService->reserve($branch, $product, 2);

    expect(branchStock($defaultBranch, $product)->stock)->toBe('10.000')
        ->and(branchStock($defaultBranch, $product)->reserved_stock)->toBe('0.000')
        ->and(branchStock($branch, $product)->stock)->toBe('20.000')
        ->and(branchStock($branch, $product)->reserved_stock)->toBe('2.000')
        ->and($stockService->availableStock($branch, $product))->toBe(18.0)
        ->and((float) $product->fresh()->stock)->toBe(30.0)
        ->and((float) $product->fresh()->reserved_stock)->toBe(2.0);
});

test('sale consumes only the stock of its branch', function () {
    $business = Business::factory()->create();
    $defaultBranch = $business->defaultBranch;
    $branch = createStockBranch($business, 'centro');
    $user = User::factory()->businessAdmin($business->id)->create();
    $product = createStockProduct($business, 10);
    app(BranchProductStockService::class)->adjust($branch, $product, 20);

    app(SaleService::class)->createSale($business, $user, [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 2,
        ]],
        'payment_status' => Sale::PAYMENT_STATUS_PAID,
        'payment_method' => Sale::PAYMENT_METHOD_CASH,
    ], $branch);

    expect(branchStock($defaultBranch, $product)->stock)->toBe('10.000')
        ->and(branchStock($branch, $product)->stock)->toBe('18.000')
        ->and((float) $product->fresh()->stock)->toBe(28.0);
});

test('stock transfer moves units without changing the legacy total', function () {
    $business = Business::factory()->create();
    $defaultBranch = $business->defaultBranch;
    $branch = createStockBranch($business, 'centro');
    $product = createStockProduct($business, 10);

    app(BranchProductStockService::class)->transfer($defaultBranch, $branch, $product, 4);

    expect(branchStock($defaultBranch, $product)->stock)->toBe('6.000')
        ->and(branchStock($branch, $product)->stock)->toBe('4.000')
        ->and((float) $product->fresh()->stock)->toBe(10.0);
});

function createStockBranch(Business $business, string $code): Branch
{
    return Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Sucursal '.ucfirst($code),
        'code' => $code,
        'is_active' => true,
        'is_default' => false,
    ]);
}

function createStockProduct(Business $business, float $stock): Product
{
    return Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Producto stock '.fake()->unique()->word(),
        'slug' => fake()->unique()->slug(),
        'unit_type' => 'unit',
        'sale_price' => 100,
        'cost_price' => 50,
        'stock' => $stock,
        'reserved_stock' => 0,
        'min_stock' => 1,
        'is_active' => true,
    ]);
}

function branchStock(Branch $branch, Product $product): BranchProductStock
{
    return BranchProductStock::query()
        ->where('branch_id', $branch->id)
        ->where('product_id', $product->id)
        ->firstOrFail();
}
