<?php

use App\Models\Business;
use App\Models\BusinessFeature;
use App\Models\BusinessPaymentDestination;
use App\Models\BusinessSaleSector;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('sale create page only exposes advanced sale settings for enabled businesses', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();

    $this->actingAs($admin)->get('/sales/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Create')
            ->where('advanced_sale_settings.enabled', false)
        );

    BusinessFeature::query()->create([
        'business_id' => $business->id,
        'feature' => BusinessFeature::ADVANCED_SALE_SETTINGS,
        'is_enabled' => true,
    ]);

    BusinessSaleSector::query()->create([
        'business_id' => $business->id,
        'name' => 'Almacen',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    BusinessPaymentDestination::query()->create([
        'business_id' => $business->id,
        'name' => 'Mercado Pago',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $this->actingAs($admin)->get('/sales/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Create')
            ->where('advanced_sale_settings.enabled', true)
            ->where('advanced_sale_settings.sale_sectors.0.name', 'Almacen')
            ->where('advanced_sale_settings.payment_destinations.0.name', 'Mercado Pago')
        );
});

test('enabled advanced sale settings require sector and payment destination for transfer sales', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();

    BusinessFeature::query()->create([
        'business_id' => $business->id,
        'feature' => BusinessFeature::ADVANCED_SALE_SETTINGS,
        'is_enabled' => true,
    ]);

    BusinessSaleSector::query()->create([
        'business_id' => $business->id,
        'name' => 'Almacen',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    BusinessPaymentDestination::query()->create([
        'business_id' => $business->id,
        'name' => 'Mercado Pago',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $product = Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Fideos',
        'slug' => 'fideos-advanced-sale',
        'unit_type' => 'unit',
        'sale_price' => 1200,
        'cost_price' => 800,
        'stock' => 10,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->from('/sales/create')
        ->post('/sales', [
            'payment_method' => 'transfer',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 1200,
            ]],
        ])
        ->assertSessionHasErrors(['sale_sector_id', 'payment_destination_id']);
});

test('enabled advanced sale settings ignore payment destination for cash sales', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();

    BusinessFeature::query()->create([
        'business_id' => $business->id,
        'feature' => BusinessFeature::ADVANCED_SALE_SETTINGS,
        'is_enabled' => true,
    ]);

    $sector = BusinessSaleSector::query()->create([
        'business_id' => $business->id,
        'name' => 'Almacen',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $destination = BusinessPaymentDestination::query()->create([
        'business_id' => $business->id,
        'name' => 'Mercado Pago',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $product = Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Fideos',
        'slug' => 'fideos-advanced-sale-cash',
        'unit_type' => 'unit',
        'sale_price' => 1200,
        'cost_price' => 800,
        'stock' => 10,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->post('/sales', [
            'payment_method' => 'cash',
            'sale_sector_id' => $sector->id,
            'payment_destination_id' => $destination->id,
            'amount_received' => 1500,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 1200,
            ]],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $sale = Sale::query()->firstOrFail();

    expect($sale->sale_sector_id)->toBe($sector->id);
    expect($sale->payment_method)->toBe('cash');
    expect($sale->payment_destination_id)->toBeNull();
});

test('advanced sale settings reject sectors and destinations from another business', function () {
    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();
    $adminA = User::factory()->businessAdmin($businessA->id)->create();

    BusinessFeature::query()->create([
        'business_id' => $businessA->id,
        'feature' => BusinessFeature::ADVANCED_SALE_SETTINGS,
        'is_enabled' => true,
    ]);

    BusinessFeature::query()->create([
        'business_id' => $businessB->id,
        'feature' => BusinessFeature::ADVANCED_SALE_SETTINGS,
        'is_enabled' => true,
    ]);

    $foreignSector = BusinessSaleSector::query()->create([
        'business_id' => $businessB->id,
        'name' => 'Sector B',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $foreignDestination = BusinessPaymentDestination::query()->create([
        'business_id' => $businessB->id,
        'name' => 'Cuenta B',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $product = Product::query()->create([
        'business_id' => $businessA->id,
        'name' => 'Arroz',
        'slug' => 'arroz-advanced-sale',
        'unit_type' => 'unit',
        'sale_price' => 900,
        'cost_price' => 500,
        'stock' => 10,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($adminA)
        ->from('/sales/create')
        ->post('/sales', [
            'payment_method' => 'transfer',
            'sale_sector_id' => $foreignSector->id,
            'payment_destination_id' => $foreignDestination->id,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 900,
            ]],
        ])
        ->assertSessionHasErrors(['sale_sector_id', 'payment_destination_id']);
});

test('sales index and dashboard include advanced sale breakdown when feature is enabled', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();

    BusinessFeature::query()->create([
        'business_id' => $business->id,
        'feature' => BusinessFeature::ADVANCED_SALE_SETTINGS,
        'is_enabled' => true,
    ]);

    $sectorA = BusinessSaleSector::query()->create([
        'business_id' => $business->id,
        'name' => 'Almacen',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $sectorB = BusinessSaleSector::query()->create([
        'business_id' => $business->id,
        'name' => 'Viviendas',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $destinationA = BusinessPaymentDestination::query()->create([
        'business_id' => $business->id,
        'name' => 'Mercado Pago Almacen',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $destinationB = BusinessPaymentDestination::query()->create([
        'business_id' => $business->id,
        'name' => 'Transferencia Viviendas',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    Sale::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'sale_sector_id' => $sectorA->id,
        'sale_number' => 'S-500001',
        'payment_method' => 'transfer',
        'payment_destination_id' => $destinationA->id,
        'subtotal' => 1000,
        'discount' => 0,
        'total' => 1000,
        'sold_at' => now(),
    ]);

    Sale::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'sale_sector_id' => $sectorB->id,
        'sale_number' => 'S-500002',
        'payment_method' => 'transfer',
        'payment_destination_id' => $destinationB->id,
        'subtotal' => 2500,
        'discount' => 0,
        'total' => 2500,
        'sold_at' => now(),
    ]);

    $this->actingAs($admin)->get('/sales')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Index')
            ->where('advanced_sale_settings.enabled', true)
            ->where('sales.data.0.sale_sector', 'Viviendas')
            ->where('sales.data.0.payment_destination', 'Transferencia Viviendas')
            ->where('monthly_summary.total', 3500)
            ->where('monthly_summary.by_sector.0.name', 'Almacen')
        );

    $this->actingAs($admin)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Index')
            ->where('advanced_sales.enabled', true)
            ->where('advanced_sales.sales_by_sector.0.name', 'Almacen')
            ->where('advanced_sales.sales_by_sector.0.total', 1000)
            ->where('advanced_sales.sales_by_payment_destination.1.name', 'Transferencia Viviendas')
            ->where('latest_sales.0.sale_sector', 'Viviendas')
        );
});
