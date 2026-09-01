<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\BranchProductStockService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use Illuminate\Validation\ValidationException;

test('sale and its stock movement use the selected branch', function () {
    $business = Business::factory()->create();
    $branch = createOperationalBranch($business, 'centro');
    $user = User::factory()->businessAdmin($business->id)->create();
    $product = createOperationalProduct($business, 10);
    app(BranchProductStockService::class)->adjust($branch, $product, 10);

    $sale = app(SaleService::class)->createSale($business, $user, [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 2,
        ]],
        'payment_status' => Sale::PAYMENT_STATUS_PAID,
        'payment_method' => Sale::PAYMENT_METHOD_CASH,
    ], $branch);

    expect($sale->branch_id)->toBe($branch->id)
        ->and(StockMovement::query()->where('reference_type', Sale::class)->where('reference_id', $sale->id)->value('branch_id'))
        ->toBe($branch->id);
});

test('purchase and its stock movement use the selected branch', function () {
    $business = Business::factory()->create();
    $branch = createOperationalBranch($business, 'centro');
    $user = User::factory()->businessAdmin($business->id)->create();
    $supplier = Supplier::query()->create([
        'business_id' => $business->id,
        'name' => 'Proveedor sucursal',
    ]);
    $product = createOperationalProduct($business, 0);

    $purchase = app(PurchaseService::class)->createPurchase($business, $user, [
        'supplier_id' => $supplier->id,
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_cost' => 100,
        ]],
    ], $branch);

    expect($purchase->branch_id)->toBe($branch->id)
        ->and(StockMovement::query()->where('reference_type', Purchase::class)->where('reference_id', $purchase->id)->value('branch_id'))
        ->toBe($branch->id);
});

test('operation services reject a branch from another business', function () {
    $business = Business::factory()->create();
    $foreignBusiness = Business::factory()->create();
    $user = User::factory()->businessAdmin($business->id)->create();
    $product = createOperationalProduct($business, 10);

    expect(fn () => app(SaleService::class)->createSale($business, $user, [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
        ]],
        'payment_status' => Sale::PAYMENT_STATUS_PAID,
        'payment_method' => Sale::PAYMENT_METHOD_CASH,
    ], $foreignBusiness->defaultBranch))->toThrow(ValidationException::class);
});

test('legacy direct operation writes use the business default branch', function () {
    $business = Business::factory()->create();
    $user = User::factory()->businessAdmin($business->id)->create();
    $product = createOperationalProduct($business, 1);

    $sale = Sale::query()->create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'sale_number' => 'S-LEGACY-001',
        'subtotal' => 100,
        'discount' => 0,
        'total' => 100,
        'sold_at' => now(),
    ]);
    $purchase = Purchase::query()->create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'purchase_number' => 'P-LEGACY-001',
        'subtotal' => 100,
        'total' => 100,
        'purchased_at' => now(),
    ]);
    $movement = StockMovement::query()->create([
        'business_id' => $business->id,
        'product_id' => $product->id,
        'type' => 'adjustment',
        'quantity' => 1,
        'stock_before' => 0,
        'stock_after' => 1,
        'created_by' => $user->id,
    ]);

    expect($sale->branch_id)->toBe($business->defaultBranch->id)
        ->and($purchase->branch_id)->toBe($business->defaultBranch->id)
        ->and($movement->branch_id)->toBe($business->defaultBranch->id);
});

function createOperationalBranch(Business $business, string $code): Branch
{
    return Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Sucursal '.ucfirst($code),
        'code' => $code,
        'is_active' => true,
        'is_default' => false,
    ]);
}

function createOperationalProduct(Business $business, float $stock): Product
{
    return Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Producto sucursal '.fake()->unique()->word(),
        'slug' => fake()->unique()->slug(),
        'unit_type' => 'unit',
        'sale_price' => 150,
        'cost_price' => 100,
        'stock' => $stock,
        'min_stock' => 0,
        'is_active' => true,
    ]);
}
