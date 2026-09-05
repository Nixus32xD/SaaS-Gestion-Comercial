<?php

use App\Models\Branch;
use App\Models\BranchProductStock;
use App\Models\Business;
use App\Models\InventoryTransfer;
use App\Models\InventoryTransferBatch;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductBatchMovement;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\BranchProductStockService;
use App\Services\InventoryTransferService;
use App\Services\ProductBatchService;
use Illuminate\Validation\ValidationException;

test('inventory transfer moves branch stock, preserves the aggregate and records both movements', function () {
    $business = Business::factory()->create();
    $fromBranch = $business->defaultBranch;
    $toBranch = inventoryTransferBranch($business, 'centro');
    $user = User::factory()->businessAdmin($business->id)->create();
    $product = inventoryTransferProduct($business, 10);

    $transfer = inventoryTransferService()->transfer($business, $fromBranch, $toBranch, $product, $user, [
        'quantity' => 4,
        'idempotency_key' => 'transfer-stock-001',
        'notes' => 'Reposición de mostrador',
    ]);

    expect(inventoryTransferStock($fromBranch, $product)->stock)->toBe('6.000')
        ->and(inventoryTransferStock($toBranch, $product)->stock)->toBe('4.000')
        ->and((float) $product->fresh()->stock)->toBe(10.0)
        ->and($transfer->reference)->not->toBeEmpty()
        ->and((float) $transfer->from_reserved_stock_snapshot)->toBe(0.0)
        ->and(StockMovement::query()->where('reference_type', InventoryTransfer::class)->where('reference_id', $transfer->id)->count())->toBe(2)
        ->and(StockMovement::query()->where('reference_id', $transfer->id)->where('branch_id', $fromBranch->id)->value('type'))->toBe('transfer_out')
        ->and(StockMovement::query()->where('reference_id', $transfer->id)->where('branch_id', $toBranch->id)->value('type'))->toBe('transfer_in');
});

test('inventory transfer consumes source batches with FEFO and recreates their trace in destination', function () {
    $business = Business::factory()->create();
    $fromBranch = $business->defaultBranch;
    $toBranch = inventoryTransferBranch($business, 'centro');
    $user = User::factory()->businessAdmin($business->id)->create();
    $product = inventoryTransferProduct($business, 0);
    app(BranchProductStockService::class)->adjust($fromBranch, $product, 5);
    $batches = app(ProductBatchService::class);
    $first = $batches->receiveStock($business, $fromBranch, $product, 2, [
        'batch_code' => 'FEFO-01', 'expires_at' => '2026-09-01', 'unit_cost' => 20,
    ]);
    $second = $batches->receiveStock($business, $fromBranch, $product, 3, [
        'batch_code' => 'FEFO-02', 'expires_at' => '2026-10-01', 'unit_cost' => 25,
    ]);

    $transfer = inventoryTransferService()->transfer($business, $fromBranch, $toBranch, $product, $user, [
        'quantity' => 3,
        'idempotency_key' => 'transfer-fefo-001',
    ]);

    $targetFirst = ProductBatch::query()->where('branch_id', $toBranch->id)->where('batch_code', 'FEFO-01')->firstOrFail();
    $targetSecond = ProductBatch::query()->where('branch_id', $toBranch->id)->where('batch_code', 'FEFO-02')->firstOrFail();

    expect($first?->fresh()->quantity)->toBe('0.000')
        ->and($second?->fresh()->quantity)->toBe('2.000')
        ->and($targetFirst->quantity)->toBe('2.000')
        ->and($targetFirst->expires_at?->toDateString())->toBe('2026-09-01')
        ->and($targetFirst->unit_cost)->toBe('20.00')
        ->and($targetSecond->quantity)->toBe('1.000')
        ->and(InventoryTransferBatch::query()->where('inventory_transfer_id', $transfer->id)->count())->toBe(2)
        ->and(ProductBatchMovement::query()->where('reference_type', InventoryTransfer::class)->where('reference_id', $transfer->id)->count())->toBe(4)
        ->and(inventoryTransferStock($fromBranch, $product)->stock)->toBe('2.000')
        ->and(inventoryTransferStock($toBranch, $product)->stock)->toBe('3.000');
});

test('inventory transfer does not merge a destination batch with incompatible trace data', function () {
    $business = Business::factory()->create();
    $fromBranch = $business->defaultBranch;
    $toBranch = inventoryTransferBranch($business, 'centro');
    $user = User::factory()->businessAdmin($business->id)->create();
    $product = inventoryTransferProduct($business, 0);
    app(BranchProductStockService::class)->adjust($fromBranch, $product, 2);
    app(BranchProductStockService::class)->adjust($toBranch, $product, 1);
    $batches = app(ProductBatchService::class);
    $batches->receiveStock($business, $fromBranch, $product, 2, ['batch_code' => 'MISMO-LOTE', 'expires_at' => '2026-10-01', 'unit_cost' => 30]);
    $existing = $batches->receiveStock($business, $toBranch, $product, 1, ['batch_code' => 'MISMO-LOTE', 'expires_at' => '2026-10-01', 'unit_cost' => 10]);

    $transfer = inventoryTransferService()->transfer($business, $fromBranch, $toBranch, $product, $user, [
        'quantity' => 2,
        'idempotency_key' => 'transfer-incompatible-batch-001',
    ]);
    $allocation = InventoryTransferBatch::query()->where('inventory_transfer_id', $transfer->id)->firstOrFail();

    expect($existing?->fresh()->quantity)->toBe('1.000')
        ->and($allocation->source_batch_code)->toBe('MISMO-LOTE')
        ->and($allocation->destination_batch_code)->not->toBe('MISMO-LOTE')
        ->and(ProductBatch::query()->whereKey($allocation->destination_product_batch_id)->value('unit_cost'))->toBe('30.00');
});

test('inventory transfer rejects insufficient available stock and does not use reserved stock', function () {
    $business = Business::factory()->create();
    $fromBranch = $business->defaultBranch;
    $toBranch = inventoryTransferBranch($business, 'centro');
    $user = User::factory()->businessAdmin($business->id)->create();
    $product = inventoryTransferProduct($business, 5);
    app(BranchProductStockService::class)->reserve($fromBranch, $product, 3);

    expect(fn () => inventoryTransferService()->transfer($business, $fromBranch, $toBranch, $product, $user, [
        'quantity' => 3,
        'idempotency_key' => 'transfer-reserved-001',
    ]))->toThrow(ValidationException::class);

    expect(inventoryTransferStock($fromBranch, $product)->stock)->toBe('5.000')
        ->and(inventoryTransferStock($fromBranch, $product)->reserved_stock)->toBe('3.000')
        ->and(InventoryTransfer::query()->count())->toBe(0);
});

test('inventory transfer rejects cross business branches and stale HTTP destinations', function () {
    $business = Business::factory()->create();
    $foreignBusiness = Business::factory()->create();
    $fromBranch = $business->defaultBranch;
    $user = User::factory()->businessAdmin($business->id)->create();
    $product = inventoryTransferProduct($business, 5);

    expect(fn () => inventoryTransferService()->transfer($business, $fromBranch, $foreignBusiness->defaultBranch, $product, $user, [
        'quantity' => 1,
        'idempotency_key' => 'transfer-cross-business-service',
    ]))->toThrow(ValidationException::class);

    $this->actingAs($user)
        ->withSession(['business_id' => $business->id, 'branch_id' => $fromBranch->id])
        ->post(route('inventory.transfers.store'), [
            'to_branch_id' => $foreignBusiness->defaultBranch->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'idempotency_key' => 'transfer-cross-business-http',
            'expected_from_branch_id' => $fromBranch->id,
        ])
        ->assertForbidden();
});

test('inventory transfer respects product precision and idempotency', function () {
    $business = Business::factory()->create();
    $fromBranch = $business->defaultBranch;
    $toBranch = inventoryTransferBranch($business, 'centro');
    $user = User::factory()->businessAdmin($business->id)->create();
    $kilograms = inventoryTransferProduct($business, 2, 'weight', 'kg');
    $grams = inventoryTransferProduct($business, 20, 'weight', 'g');

    expect(fn () => inventoryTransferService()->transfer($business, $fromBranch, $toBranch, $grams, $user, [
        'quantity' => 1.5,
        'idempotency_key' => 'transfer-grams-invalid',
    ]))->toThrow(ValidationException::class);

    $first = inventoryTransferService()->transfer($business, $fromBranch, $toBranch, $kilograms, $user, [
        'quantity' => 1.125,
        'idempotency_key' => 'transfer-kilos-idempotent',
        'notes' => 'Fraccionable',
    ]);
    $retry = inventoryTransferService()->transfer($business, $fromBranch, $toBranch, $kilograms, $user, [
        'quantity' => 1.125,
        'idempotency_key' => 'transfer-kilos-idempotent',
        'notes' => 'Fraccionable',
    ]);

    expect($retry->id)->toBe($first->id)
        ->and(inventoryTransferStock($fromBranch, $kilograms)->stock)->toBe('0.875')
        ->and(inventoryTransferStock($toBranch, $kilograms)->stock)->toBe('1.125')
        ->and(StockMovement::query()->where('reference_id', $first->id)->count())->toBe(2);

    expect(fn () => inventoryTransferService()->transfer($business, $fromBranch, $toBranch, $kilograms, $user, [
        'quantity' => 1,
        'idempotency_key' => 'transfer-kilos-idempotent',
        'notes' => 'Fraccionable',
    ]))->toThrow(ValidationException::class);
});

test('serialized transfer attempts cannot make the source stock negative', function () {
    $business = Business::factory()->create();
    $fromBranch = $business->defaultBranch;
    $toBranch = inventoryTransferBranch($business, 'centro');
    $user = User::factory()->businessAdmin($business->id)->create();
    $product = inventoryTransferProduct($business, 8);

    inventoryTransferService()->transfer($business, $fromBranch, $toBranch, $product, $user, [
        'quantity' => 6,
        'idempotency_key' => 'transfer-serialized-first',
    ]);

    expect(fn () => inventoryTransferService()->transfer($business, $fromBranch, $toBranch, $product, $user, [
        'quantity' => 3,
        'idempotency_key' => 'transfer-serialized-second',
    ]))->toThrow(ValidationException::class);

    expect(inventoryTransferStock($fromBranch, $product)->stock)->toBe('2.000')
        ->and(inventoryTransferStock($toBranch, $product)->stock)->toBe('6.000')
        ->and((float) $product->fresh()->stock)->toBe(8.0);
});

test('inventory transfer rolls back stock and traceability when batch processing fails', function () {
    $business = Business::factory()->create();
    $fromBranch = $business->defaultBranch;
    $toBranch = inventoryTransferBranch($business, 'centro');
    $user = User::factory()->businessAdmin($business->id)->create();
    $product = inventoryTransferProduct($business, 5);
    $batches = \Mockery::mock(ProductBatchService::class);
    $batches->shouldReceive('transferStock')->once()->andThrow(new RuntimeException('Proveedor de lotes no disponible'));
    app()->instance(ProductBatchService::class, $batches);

    expect(fn () => app(InventoryTransferService::class)->transfer($business, $fromBranch, $toBranch, $product, $user, [
        'quantity' => 2,
        'idempotency_key' => 'transfer-rollback-001',
    ]))->toThrow(RuntimeException::class);

    expect(inventoryTransferStock($fromBranch, $product)->stock)->toBe('5.000')
        ->and(BranchProductStock::query()->where('branch_id', $toBranch->id)->where('product_id', $product->id)->exists())->toBeFalse()
        ->and(InventoryTransfer::query()->count())->toBe(0)
        ->and(StockMovement::query()->count())->toBe(0);
});

test('inventory transfer index scopes products and history to active business and branch', function () {
    $business = Business::factory()->create();
    $foreignBusiness = Business::factory()->create();
    $branch = $business->defaultBranch;
    $user = User::factory()->businessAdmin($business->id)->create();
    $product = inventoryTransferProduct($business, 5);
    inventoryTransferProduct($foreignBusiness, 5);

    $this->actingAs($user)
        ->withSession(['business_id' => $business->id, 'branch_id' => $branch->id])
        ->get(route('inventory.transfers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('source_branch.id', $branch->id)
            ->has('products', 1)
            ->where('products.0.id', $product->id)
            ->has('transfers.data', 0));
});

function inventoryTransferService(): InventoryTransferService
{
    return app(InventoryTransferService::class);
}

function inventoryTransferBranch(Business $business, string $code): Branch
{
    return Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Sucursal '.ucfirst($code),
        'code' => $code,
        'is_active' => true,
        'is_default' => false,
    ]);
}

function inventoryTransferProduct(Business $business, float $stock, string $unitType = 'unit', ?string $weightUnit = null): Product
{
    return Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Producto transferencia '.fake()->unique()->word(),
        'slug' => fake()->unique()->slug(),
        'unit_type' => $unitType,
        'weight_unit' => $weightUnit,
        'sale_price' => 100,
        'cost_price' => 50,
        'stock' => $stock,
        'reserved_stock' => 0,
        'min_stock' => 0,
        'is_active' => true,
    ]);
}

function inventoryTransferStock(Branch $branch, Product $product): BranchProductStock
{
    return BranchProductStock::query()
        ->where('branch_id', $branch->id)
        ->where('product_id', $product->id)
        ->firstOrFail();
}
