<?php

use App\Models\Business;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductBatchCorrection;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('business user can correct an active product batch expiry date', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();

    $product = Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Yogur',
        'slug' => 'yogur-lote',
        'unit_type' => 'unit',
        'sale_price' => 2500,
        'cost_price' => 1800,
        'stock' => 8,
        'min_stock' => 1,
        'expiry_alert_days' => 15,
        'is_active' => true,
    ]);

    $batch = ProductBatch::query()->create([
        'business_id' => $business->id,
        'product_id' => $product->id,
        'batch_code' => 'LOT-2025',
        'expires_at' => '2025-04-10',
        'quantity' => 8,
        'unit_cost' => 1700,
    ]);

    $this->actingAs($admin)
        ->put(route('products.batches.update', [$product, $batch]), [
            'batch_code' => 'LOT-2025',
            'expires_at' => '2027-04-10',
            'unit_cost' => 1900,
            'reason' => 'Se cargo mal el ano al recibir la mercaderia.',
        ])
        ->assertRedirect(route('products.edit', $product));

    $batch->refresh();
    $correction = ProductBatchCorrection::query()->firstOrFail();

    expect($batch->expires_at?->toDateString())->toBe('2027-04-10');
    expect((float) $batch->unit_cost)->toBe(1900.0);
    expect($correction->previous_expires_at?->toDateString())->toBe('2025-04-10');
    expect($correction->new_expires_at?->toDateString())->toBe('2027-04-10');
    expect($correction->reason)->toBe('Se cargo mal el ano al recibir la mercaderia.');
});

test('business user cannot correct a batch from another business', function () {
    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();
    $adminA = User::factory()->businessAdmin($businessA->id)->create();

    $productA = Product::query()->create([
        'business_id' => $businessA->id,
        'name' => 'Producto A',
        'slug' => 'producto-a-lote',
        'unit_type' => 'unit',
        'sale_price' => 1200,
        'cost_price' => 900,
        'stock' => 3,
        'min_stock' => 0,
        'is_active' => true,
    ]);

    $productB = Product::query()->create([
        'business_id' => $businessB->id,
        'name' => 'Producto B',
        'slug' => 'producto-b-lote',
        'unit_type' => 'unit',
        'sale_price' => 1800,
        'cost_price' => 1200,
        'stock' => 5,
        'min_stock' => 0,
        'is_active' => true,
    ]);

    $batchB = ProductBatch::query()->create([
        'business_id' => $businessB->id,
        'product_id' => $productB->id,
        'batch_code' => 'LOT-B',
        'expires_at' => '2026-08-01',
        'quantity' => 5,
        'unit_cost' => 1200,
    ]);

    $this->actingAs($adminA)
        ->put(route('products.batches.update', [$productA, $batchB]), [
            'batch_code' => 'LOT-B-CORR',
            'expires_at' => '2027-08-01',
            'unit_cost' => 1300,
        ])
        ->assertForbidden();
});

test('business user can review product batch correction history for own business only', function () {
    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();
    $adminA = User::factory()->businessAdmin($businessA->id)->create();
    $adminB = User::factory()->businessAdmin($businessB->id)->create();

    $productA = Product::query()->create([
        'business_id' => $businessA->id,
        'name' => 'Queso',
        'slug' => 'queso-historial',
        'unit_type' => 'unit',
        'sale_price' => 3000,
        'cost_price' => 2000,
        'stock' => 6,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    $batchA = ProductBatch::query()->create([
        'business_id' => $businessA->id,
        'product_id' => $productA->id,
        'batch_code' => 'Q-001',
        'expires_at' => '2026-05-10',
        'quantity' => 6,
        'unit_cost' => 2000,
    ]);

    ProductBatchCorrection::query()->create([
        'business_id' => $businessA->id,
        'product_id' => $productA->id,
        'product_batch_id' => $batchA->id,
        'corrected_by' => $adminA->id,
        'previous_batch_code' => 'Q-001',
        'new_batch_code' => 'Q-001',
        'previous_expires_at' => '2026-05-10',
        'new_expires_at' => '2027-05-10',
        'previous_unit_cost' => 2000,
        'new_unit_cost' => 2100,
        'reason' => 'Control interno de vencimiento.',
    ]);

    $this->actingAs($adminA)
        ->get(route('products.batch-corrections.index', $productA))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Products/BatchCorrections')
            ->where('product.name', 'Queso')
            ->where('corrections.data.0.reason', 'Control interno de vencimiento.')
        );

    $productB = Product::query()->create([
        'business_id' => $businessB->id,
        'name' => 'Manteca',
        'slug' => 'manteca-historial',
        'unit_type' => 'unit',
        'sale_price' => 2500,
        'cost_price' => 1900,
        'stock' => 4,
        'min_stock' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($adminA)
        ->get(route('products.batch-corrections.index', $productB))
        ->assertForbidden();

    $this->actingAs($adminB)
        ->get(route('products.batch-corrections.index', $productA))
        ->assertForbidden();
});
