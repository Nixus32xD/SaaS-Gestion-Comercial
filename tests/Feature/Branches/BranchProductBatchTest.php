<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductBatchMovement;
use App\Models\Sale;
use App\Models\User;
use App\Services\BranchProductStockService;
use App\Services\ProductBatchService;
use App\Services\SaleService;

test('FEFO consumes only batches from the sale branch', function () {
    $business = Business::factory()->create();
    $branchA = $business->defaultBranch;
    $branchB = createBatchBranch($business, 'centro');
    $user = User::factory()->businessAdmin($business->id)->create();
    $product = createBatchProduct($business);

    app(BranchProductStockService::class)->adjust($branchA, $product, 3);
    app(BranchProductStockService::class)->adjust($branchB, $product, 5);

    $batchService = app(ProductBatchService::class);
    $batchA = $batchService->receiveStock($business, $branchA, $product, 3, [
        'batch_code' => 'LOTE-COMPARTIDO',
        'expires_at' => '2026-09-01',
    ]);
    $batchB = $batchService->receiveStock($business, $branchB, $product, 5, [
        'batch_code' => 'LOTE-COMPARTIDO',
        'expires_at' => '2026-08-28',
    ]);

    app(SaleService::class)->createSale($business, $user, [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 2,
        ]],
        'payment_status' => Sale::PAYMENT_STATUS_PAID,
        'payment_method' => Sale::PAYMENT_METHOD_CASH,
    ], $branchA);

    expect($batchA?->fresh()->quantity)->toBe('1.000')
        ->and($batchB?->fresh()->quantity)->toBe('5.000')
        ->and(ProductBatchMovement::query()->where('product_batch_id', $batchA?->id)->latest('id')->value('branch_id'))
        ->toBe($branchA->id)
        ->and(ProductBatchMovement::query()->where('product_batch_id', $batchB?->id)->latest('id')->value('branch_id'))
        ->toBe($branchB->id);
});

test('a batch correction cannot cross the active branch', function () {
    $business = Business::factory()->create();
    $branchA = $business->defaultBranch;
    $branchB = createBatchBranch($business, 'centro');
    $product = createBatchProduct($business);
    $batch = ProductBatch::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branchB->id,
        'product_id' => $product->id,
        'batch_code' => 'CENTRO-01',
        'quantity' => 1,
    ]);

    $this->actingAs(User::factory()->businessAdmin($business->id)->create())
        ->withSession(['business_id' => $business->id, 'branch_id' => $branchA->id])
        ->put(route('products.batches.update', [$product, $batch]), [
            'batch_code' => 'CENTRO-02',
        ])
        ->assertNotFound();
});

function createBatchBranch(Business $business, string $code): Branch
{
    return Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Sucursal '.ucfirst($code),
        'code' => $code,
        'is_active' => true,
        'is_default' => false,
    ]);
}

function createBatchProduct(Business $business): Product
{
    return Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Producto lote '.fake()->unique()->word(),
        'slug' => fake()->unique()->slug(),
        'unit_type' => 'unit',
        'sale_price' => 100,
        'cost_price' => 50,
        'stock' => 0,
        'min_stock' => 0,
        'is_active' => true,
    ]);
}
