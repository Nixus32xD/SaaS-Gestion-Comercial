<?php

use App\Models\Business;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Services\ProductExpirationAlertService;

test('expiration alert service reports expired and upcoming tracked batches', function () {
    $this->travelTo(now()->create(2026, 3, 22, 10, 0, 0));

    $business = Business::factory()->create();
    $product = Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Fiambre',
        'slug' => 'fiambre',
        'unit_type' => 'unit',
        'sale_price' => 3500,
        'cost_price' => 2200,
        'stock' => 12,
        'min_stock' => 0,
        'expiry_alert_days' => 7,
        'is_active' => true,
    ]);

    ProductBatch::query()->create([
        'business_id' => $business->id,
        'product_id' => $product->id,
        'batch_code' => 'VENC-1',
        'expires_at' => '2026-03-20',
        'quantity' => 2,
        'unit_cost' => 2000,
    ]);

    ProductBatch::query()->create([
        'business_id' => $business->id,
        'product_id' => $product->id,
        'batch_code' => 'PROX-1',
        'expires_at' => '2026-03-26',
        'quantity' => 4,
        'unit_cost' => 2100,
    ]);

    ProductBatch::query()->create([
        'business_id' => $business->id,
        'product_id' => $product->id,
        'batch_code' => 'LEJ-1',
        'expires_at' => '2026-05-15',
        'quantity' => 6,
        'unit_cost' => 2200,
    ]);

    /** @var ProductExpirationAlertService $service */
    $service = app(ProductExpirationAlertService::class);
    $alerts = $service->listForBusiness($business->id, 10)->values();
    $summary = $service->summarizeForBusiness($business->id);

    expect($alerts)->toHaveCount(2);
    expect($alerts->pluck('batch_code')->all())->toBe(['VENC-1', 'PROX-1']);
    expect($alerts->pluck('status')->all())->toBe(['expired', 'upcoming']);
    expect($summary)->toMatchArray([
        'expired' => 1,
        'within_7_days' => 1,
        'within_15_days' => 1,
        'within_30_days' => 1,
    ]);

    $this->travelBack();
});
