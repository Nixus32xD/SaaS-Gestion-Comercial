<?php

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
