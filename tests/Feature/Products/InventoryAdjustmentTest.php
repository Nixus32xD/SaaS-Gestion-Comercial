<?php

use App\Models\Branch;
use App\Models\BranchProductStock;
use App\Models\Business;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductBatchMovement;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\BranchProductStockService;
use App\Services\InventoryAdjustmentService;
use App\Services\ProductBatchService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

test('manual adjustment changes only the active branch and keeps the legacy aggregate synchronized', function () {
    $business = Business::factory()->create();
    $branchA = $business->defaultBranch;
    $branchB = inventoryAdjustmentBranch($business, 'centro');
    $user = User::factory()->businessAdmin($business->id)->create();
    $product = inventoryAdjustmentProduct($business, 10);
    app(BranchProductStockService::class)->adjust($branchB, $product, 20);

    $this->actingAs($user)->withSession(['business_id' => $business->id, 'branch_id' => $branchB->id])
        ->post(route('products.inventory-adjustments.store', $product), inventoryAdjustmentPayload($branchB, 5))
        ->assertRedirect(route('products.edit', $product));

    $adjustment = InventoryAdjustment::query()->firstOrFail();
    $movement = StockMovement::query()->where('reference_id', $adjustment->id)->firstOrFail();
    $batchMovement = ProductBatchMovement::query()->where('reference_id', $adjustment->id)->firstOrFail();

    expect(inventoryAdjustmentStock($branchA, $product)->stock)->toBe('10.000')
        ->and(inventoryAdjustmentStock($branchB, $product)->stock)->toBe('25.000')
        ->and((float) $product->fresh()->stock)->toBe(35.0)
        ->and($adjustment->branch_id)->toBe($branchB->id)
        ->and($adjustment->created_by)->toBe($user->id)
        ->and((float) $adjustment->stock_before)->toBe(20.0)
        ->and((float) $adjustment->stock_after)->toBe(25.0)
        ->and($movement->branch_id)->toBe($branchB->id)
        ->and($movement->reference_type)->toBe(InventoryAdjustment::class)
        ->and($batchMovement->branch_id)->toBe($branchB->id)
        ->and($batchMovement->reference_type)->toBe(InventoryAdjustment::class);
});

test('negative adjustment uses FEFO from the active branch and permits historical stock without batches', function () {
    $business = Business::factory()->create();
    $branchA = $business->defaultBranch;
    $branchB = inventoryAdjustmentBranch($business, 'centro');
    $user = User::factory()->businessAdmin($business->id)->create();
    $product = inventoryAdjustmentProduct($business, 0);
    $stockService = app(BranchProductStockService::class);
    $stockService->adjust($branchA, $product, 9);
    $stockService->adjust($branchB, $product, 5);
    $batchService = app(ProductBatchService::class);
    $first = $batchService->receiveStock($business, $branchB, $product, 2, ['batch_code' => 'A-01', 'expires_at' => '2026-09-01']);
    $second = $batchService->receiveStock($business, $branchB, $product, 3, ['batch_code' => 'A-02', 'expires_at' => '2026-10-01']);
    $otherBranchBatch = $batchService->receiveStock($business, $branchA, $product, 3, ['batch_code' => 'B-01', 'expires_at' => '2026-08-01']);

    $this->actingAs($user)->withSession(['business_id' => $business->id, 'branch_id' => $branchB->id])
        ->post(route('products.inventory-adjustments.store', $product), inventoryAdjustmentPayload($branchB, -3))
        ->assertRedirect();

    expect($first?->fresh()->quantity)->toBe('0.000')
        ->and($second?->fresh()->quantity)->toBe('2.000')
        ->and($otherBranchBatch?->fresh()->quantity)->toBe('3.000')
        ->and(inventoryAdjustmentStock($branchB, $product)->stock)->toBe('2.000')
        ->and(inventoryAdjustmentStock($branchA, $product)->stock)->toBe('9.000');

    $this->actingAs($user)->withSession(['business_id' => $business->id, 'branch_id' => $branchB->id])
        ->post(route('products.inventory-adjustments.store', $product), inventoryAdjustmentPayload($branchB, -2))
        ->assertRedirect();

    expect(inventoryAdjustmentStock($branchB, $product)->stock)->toBe('0.000')
        ->and((float) ProductBatch::query()->where('branch_id', $branchB->id)->sum('quantity'))->toBe(0.0);
});

test('adjustment rejects zero, invalid measurement, negative stock, reserved stock and stale branch context', function () {
    $business = Business::factory()->create();
    $branch = $business->defaultBranch;
    $otherBranch = inventoryAdjustmentBranch($business, 'centro');
    $user = User::factory()->businessAdmin($business->id)->create();
    $product = inventoryAdjustmentProduct($business, 5);
    app(BranchProductStockService::class)->reserve($branch, $product, 3);

    foreach ([0, -6, -3, 1.5] as $delta) {
        $this->actingAs($user)->withSession(['business_id' => $business->id, 'branch_id' => $branch->id])
            ->from(route('products.inventory-adjustments.create', $product))
            ->post(route('products.inventory-adjustments.store', $product), inventoryAdjustmentPayload($branch, $delta))
            ->assertRedirect(route('products.inventory-adjustments.create', $product))
            ->assertSessionHasErrors('delta');
    }

    $this->actingAs($user)->withSession(['business_id' => $business->id, 'branch_id' => $branch->id])
        ->from(route('products.inventory-adjustments.create', $product))
        ->post(route('products.inventory-adjustments.store', $product), inventoryAdjustmentPayload($otherBranch, 1))
        ->assertRedirect(route('products.inventory-adjustments.create', $product))
        ->assertSessionHasErrors('expected_branch_id');

    expect(InventoryAdjustment::query()->count())->toBe(0)
        ->and(inventoryAdjustmentStock($branch, $product)->stock)->toBe('5.000');
});

test('adjustment respects kilogram and gram precision', function () {
    $business = Business::factory()->create();
    $branch = $business->defaultBranch;
    $user = User::factory()->businessAdmin($business->id)->create();
    $kilograms = inventoryAdjustmentProduct($business, 0, 'weight', 'kg');
    $grams = inventoryAdjustmentProduct($business, 0, 'weight', 'g');

    $this->actingAs($user)->withSession(['business_id' => $business->id, 'branch_id' => $branch->id])
        ->post(route('products.inventory-adjustments.store', $kilograms), inventoryAdjustmentPayload($branch, 1.125))
        ->assertRedirect();
    $this->actingAs($user)->withSession(['business_id' => $business->id, 'branch_id' => $branch->id])
        ->post(route('products.inventory-adjustments.store', $grams), inventoryAdjustmentPayload($branch, 25))
        ->assertRedirect();
    $this->actingAs($user)->withSession(['business_id' => $business->id, 'branch_id' => $branch->id])
        ->from(route('products.inventory-adjustments.create', $grams))
        ->post(route('products.inventory-adjustments.store', $grams), inventoryAdjustmentPayload($branch, 1.5))
        ->assertSessionHasErrors('delta');

    expect(inventoryAdjustmentStock($branch, $kilograms)->stock)->toBe('1.125')
        ->and(inventoryAdjustmentStock($branch, $grams)->stock)->toBe('25.000');
});

test('an inventory adjustment retry applies stock and traceability exactly once', function () {
    $business = Business::factory()->create();
    $branch = $business->defaultBranch;
    $user = User::factory()->businessAdmin($business->id)->create();
    $product = inventoryAdjustmentProduct($business, 10);
    $key = (string) Str::uuid();
    $payload = inventoryAdjustmentPayload($branch, 5, $key);

    $this->actingAs($user)->withSession(['business_id' => $business->id, 'branch_id' => $branch->id])
        ->post(route('products.inventory-adjustments.store', $product), $payload)
        ->assertRedirect(route('products.edit', $product));
    $this->actingAs($user)->withSession(['business_id' => $business->id, 'branch_id' => $branch->id])
        ->post(route('products.inventory-adjustments.store', $product), $payload)
        ->assertRedirect(route('products.edit', $product));

    $adjustment = InventoryAdjustment::query()->sole();

    expect(inventoryAdjustmentStock($branch, $product)->stock)->toBe('15.000')
        ->and($adjustment->idempotency_key)->toBe($key)
        ->and($adjustment->request_fingerprint)->toHaveLength(64)
        ->and(StockMovement::query()->where('reference_id', $adjustment->id)->count())->toBe(1)
        ->and(ProductBatchMovement::query()->where('reference_id', $adjustment->id)->count())->toBe(1);
});

test('an idempotency key cannot be reused for a different inventory adjustment payload', function () {
    $business = Business::factory()->create();
    $branch = $business->defaultBranch;
    $user = User::factory()->businessAdmin($business->id)->create();
    $product = inventoryAdjustmentProduct($business, 10);
    $key = (string) Str::uuid();

    $this->actingAs($user)->withSession(['business_id' => $business->id, 'branch_id' => $branch->id])
        ->post(route('products.inventory-adjustments.store', $product), inventoryAdjustmentPayload($branch, 5, $key))
        ->assertRedirect();
    $this->actingAs($user)->withSession(['business_id' => $business->id, 'branch_id' => $branch->id])
        ->from(route('products.inventory-adjustments.create', $product))
        ->post(route('products.inventory-adjustments.store', $product), inventoryAdjustmentPayload($branch, -5, $key))
        ->assertRedirect(route('products.inventory-adjustments.create', $product))
        ->assertSessionHasErrors('idempotency_key');

    expect(InventoryAdjustment::query()->count())->toBe(1)
        ->and(StockMovement::query()->count())->toBe(1)
        ->and(inventoryAdjustmentStock($branch, $product)->stock)->toBe('15.000');
});

test('an inventory adjustment requires a UUID idempotency key', function () {
    $business = Business::factory()->create();
    $branch = $business->defaultBranch;
    $user = User::factory()->businessAdmin($business->id)->create();
    $product = inventoryAdjustmentProduct($business, 10);

    $this->actingAs($user)->withSession(['business_id' => $business->id, 'branch_id' => $branch->id])
        ->from(route('products.inventory-adjustments.create', $product))
        ->post(route('products.inventory-adjustments.store', $product), inventoryAdjustmentPayload($branch, 5, 'not-a-uuid'))
        ->assertRedirect(route('products.inventory-adjustments.create', $product))
        ->assertSessionHasErrors('idempotency_key');

    expect(InventoryAdjustment::query()->count())->toBe(0)
        ->and(inventoryAdjustmentStock($branch, $product)->stock)->toBe('10.000');
});

test('the same inventory adjustment key is isolated by business but not by branch', function () {
    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();
    $branchA = $businessA->defaultBranch;
    $branchB = $businessB->defaultBranch;
    $secondBranchA = inventoryAdjustmentBranch($businessA, 'centro');
    $userA = User::factory()->businessAdmin($businessA->id)->create();
    $userB = User::factory()->businessAdmin($businessB->id)->create();
    $productA = inventoryAdjustmentProduct($businessA, 2);
    $productB = inventoryAdjustmentProduct($businessB, 2);
    $key = (string) Str::uuid();

    $first = inventoryAdjustmentService()->adjust($businessA, $branchA, $productA, $userA, inventoryAdjustmentPayload($branchA, 1, $key));
    $otherBusiness = inventoryAdjustmentService()->adjust($businessB, $branchB, $productB, $userB, inventoryAdjustmentPayload($branchB, 1, $key));

    expect(fn () => inventoryAdjustmentService()->adjust(
        $businessA,
        $secondBranchA,
        $productA,
        $userA,
        inventoryAdjustmentPayload($secondBranchA, 1, $key),
    ))->toThrow(ValidationException::class);

    expect($first->id)->not->toBe($otherBusiness->id)
        ->and(InventoryAdjustment::query()->count())->toBe(2)
        ->and(inventoryAdjustmentStock($branchA, $productA)->stock)->toBe('3.000')
        ->and(BranchProductStock::query()->where('branch_id', $secondBranchA->id)->where('product_id', $productA->id)->exists())->toBeFalse()
        ->and(inventoryAdjustmentStock($branchB, $productB)->stock)->toBe('3.000');
});

test('the unique database constraint backs a competing inventory adjustment attempt', function () {
    $business = Business::factory()->create();
    $branch = $business->defaultBranch;
    $user = User::factory()->businessAdmin($business->id)->create();
    $product = inventoryAdjustmentProduct($business, 3);
    $first = inventoryAdjustmentService()->adjust($business, $branch, $product, $user, inventoryAdjustmentPayload($branch, 2, (string) Str::uuid()));
    $competingInsert = $first->replicate();

    expect(fn () => $competingInsert->save())->toThrow(QueryException::class)
        ->and(InventoryAdjustment::query()->count())->toBe(1)
        ->and(StockMovement::query()->where('reference_id', $first->id)->count())->toBe(1)
        ->and(inventoryAdjustmentStock($branch, $product)->stock)->toBe('5.000');
});

test('a failed inventory adjustment rolls back its key and can be retried', function () {
    $business = Business::factory()->create();
    $branch = $business->defaultBranch;
    $user = User::factory()->businessAdmin($business->id)->create();
    $product = inventoryAdjustmentProduct($business, 3);
    $key = (string) Str::uuid();
    $payload = inventoryAdjustmentPayload($branch, 2, $key);
    $batches = \Mockery::mock(ProductBatchService::class);
    $batches->shouldReceive('receiveStock')->once()->andThrow(new RuntimeException('Fallo al procesar lotes'));
    app()->instance(ProductBatchService::class, $batches);

    expect(fn () => inventoryAdjustmentService()->adjust($business, $branch, $product, $user, $payload))
        ->toThrow(RuntimeException::class);

    expect(InventoryAdjustment::query()->count())->toBe(0)
        ->and(StockMovement::query()->count())->toBe(0)
        ->and(inventoryAdjustmentStock($branch, $product)->stock)->toBe('3.000');

    app()->forgetInstance(InventoryAdjustmentService::class);
    app()->forgetInstance(ProductBatchService::class);
    $retry = inventoryAdjustmentService()->adjust($business, $branch, $product, $user, $payload);

    expect($retry->idempotency_key)->toBe($key)
        ->and(InventoryAdjustment::query()->count())->toBe(1)
        ->and(inventoryAdjustmentStock($branch, $product)->stock)->toBe('5.000');
});

test('a duplicated negative adjustment cannot consume reserved stock twice', function () {
    $business = Business::factory()->create();
    $branch = $business->defaultBranch;
    $user = User::factory()->businessAdmin($business->id)->create();
    $product = inventoryAdjustmentProduct($business, 5);
    app(BranchProductStockService::class)->reserve($branch, $product, 3);
    $key = (string) Str::uuid();
    $payload = inventoryAdjustmentPayload($branch, -2, $key);

    inventoryAdjustmentService()->adjust($business, $branch, $product, $user, $payload);
    inventoryAdjustmentService()->adjust($business, $branch, $product, $user, $payload);

    expect(inventoryAdjustmentStock($branch, $product)->stock)->toBe('3.000')
        ->and(inventoryAdjustmentStock($branch, $product)->reserved_stock)->toBe('3.000')
        ->and(InventoryAdjustment::query()->count())->toBe(1)
        ->and(StockMovement::query()->count())->toBe(1);
});

test('product creation initializes stock and minimum only in the active branch', function () {
    $business = Business::factory()->create();
    $branchA = $business->defaultBranch;
    $branchB = inventoryAdjustmentBranch($business, 'centro');
    $user = User::factory()->businessAdmin($business->id)->create();

    $this->actingAs($user)->withSession(['business_id' => $business->id, 'branch_id' => $branchB->id])
        ->post(route('products.store'), [
            'name' => 'Producto de Centro',
            'unit_type' => 'unit',
            'sale_price' => 100,
            'cost_price' => 50,
            'stock' => 4,
            'min_stock' => 2,
            'is_active' => true,
        ])
        ->assertRedirect(route('products.index'));

    $product = Product::query()->where('name', 'Producto de Centro')->firstOrFail();

    expect(inventoryAdjustmentStock($branchA, $product)->stock)->toBe('0.000')
        ->and(inventoryAdjustmentStock($branchA, $product)->min_stock)->toBe('0.000')
        ->and(inventoryAdjustmentStock($branchB, $product)->stock)->toBe('4.000')
        ->and(inventoryAdjustmentStock($branchB, $product)->min_stock)->toBe('2.000')
        ->and($product->stock)->toBe('4.000');
});

function inventoryAdjustmentBranch(Business $business, string $code): Branch
{
    return Branch::query()->create(['business_id' => $business->id, 'name' => 'Sucursal '.ucfirst($code), 'code' => $code, 'is_active' => true, 'is_default' => false]);
}

function inventoryAdjustmentProduct(Business $business, float $stock, string $unitType = 'unit', ?string $weightUnit = null): Product
{
    return Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Producto ajuste '.fake()->unique()->word(),
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

function inventoryAdjustmentStock(Branch $branch, Product $product): BranchProductStock
{
    return BranchProductStock::query()->where('branch_id', $branch->id)->where('product_id', $product->id)->firstOrFail();
}

function inventoryAdjustmentService(): InventoryAdjustmentService
{
    return app(InventoryAdjustmentService::class);
}

function inventoryAdjustmentPayload(Branch $branch, float $delta, ?string $idempotencyKey = null): array
{
    return [
        'delta' => $delta,
        'reason' => 'physical_count',
        'expected_branch_id' => $branch->id,
        'idempotency_key' => $idempotencyKey ?? (string) Str::uuid(),
    ];
}
