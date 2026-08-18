<?php

use App\Models\Business;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('dashboard only exposes metrics from the authenticated business', function () {
    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();
    $adminA = User::factory()->businessAdmin($businessA->id)->create();
    $adminB = User::factory()->businessAdmin($businessB->id)->create();
    $supplierA = Supplier::query()->create([
        'business_id' => $businessA->id,
        'name' => 'Proveedor A',
    ]);
    $supplierB = Supplier::query()->create([
        'business_id' => $businessB->id,
        'name' => 'Proveedor B',
    ]);

    Product::query()->create([
        'business_id' => $businessA->id,
        'supplier_id' => $supplierA->id,
        'name' => 'Producto A',
        'slug' => 'producto-a-dashboard',
        'unit_type' => 'unit',
        'sale_price' => 100,
        'cost_price' => 50,
        'stock' => 5,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    Product::query()->create([
        'business_id' => $businessB->id,
        'supplier_id' => $supplierB->id,
        'name' => 'Producto B',
        'slug' => 'producto-b-dashboard',
        'unit_type' => 'unit',
        'sale_price' => 100,
        'cost_price' => 50,
        'stock' => 5,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    Sale::query()->create([
        'business_id' => $businessA->id,
        'user_id' => $adminA->id,
        'sale_number' => 'S-100001',
        'subtotal' => 100,
        'discount' => 0,
        'total' => 100,
        'sold_at' => now(),
    ]);

    Sale::query()->create([
        'business_id' => $businessB->id,
        'user_id' => $adminB->id,
        'sale_number' => 'S-200001',
        'subtotal' => 999,
        'discount' => 0,
        'total' => 999,
        'sold_at' => now(),
    ]);

    Purchase::query()->create([
        'business_id' => $businessA->id,
        'user_id' => $adminA->id,
        'supplier_id' => $supplierA->id,
        'purchase_number' => 'P-100001',
        'subtotal' => 80,
        'total' => 80,
        'purchased_at' => now(),
    ]);

    $this->actingAs($adminA)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Index')
            ->where('summary.today_sales', 100)
            ->where('summary.month_sales', 100)
            ->where('summary.products_count', 1)
            ->where('summary.suppliers_count', 1)
        );
});

test('dashboard exposes historical sales and purchase periods for the active business', function () {
    $this->travelTo(now()->setDate(2026, 8, 17)->setTime(10, 0));

    $business = Business::factory()->create();
    $otherBusiness = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $otherAdmin = User::factory()->businessAdmin($otherBusiness->id)->create();
    $supplier = Supplier::query()->create([
        'business_id' => $business->id,
        'name' => 'Proveedor historico',
    ]);
    $otherSupplier = Supplier::query()->create([
        'business_id' => $otherBusiness->id,
        'name' => 'Proveedor externo',
    ]);

    foreach ([
        ['number' => 'S-HIST-001', 'total' => 100, 'sold_at' => now()],
        ['number' => 'S-HIST-002', 'total' => 200, 'sold_at' => now()->subDays(10)],
        ['number' => 'S-HIST-003', 'total' => 300, 'sold_at' => now()->subDays(40)],
        ['number' => 'S-HIST-004', 'total' => 400, 'sold_at' => now()->subYear()],
    ] as $row) {
        Sale::query()->create([
            'business_id' => $business->id,
            'user_id' => $admin->id,
            'sale_number' => $row['number'],
            'subtotal' => $row['total'],
            'discount' => 0,
            'total' => $row['total'],
            'sold_at' => $row['sold_at'],
        ]);
    }

    Sale::query()->create([
        'business_id' => $otherBusiness->id,
        'user_id' => $otherAdmin->id,
        'sale_number' => 'S-HIST-OTHER',
        'subtotal' => 999,
        'discount' => 0,
        'total' => 999,
        'sold_at' => now(),
    ]);

    foreach ([
        ['number' => 'P-HIST-001', 'total' => 50, 'purchased_at' => now()],
        ['number' => 'P-HIST-002', 'total' => 25, 'purchased_at' => now()->subDays(10)],
        ['number' => 'P-HIST-003', 'total' => 30, 'purchased_at' => now()->subDays(40)],
        ['number' => 'P-HIST-004', 'total' => 20, 'purchased_at' => now()->subYear()],
    ] as $row) {
        Purchase::query()->create([
            'business_id' => $business->id,
            'user_id' => $admin->id,
            'supplier_id' => $supplier->id,
            'purchase_number' => $row['number'],
            'subtotal' => $row['total'],
            'total' => $row['total'],
            'purchased_at' => $row['purchased_at'],
        ]);
    }

    Purchase::query()->create([
        'business_id' => $otherBusiness->id,
        'user_id' => $otherAdmin->id,
        'supplier_id' => $otherSupplier->id,
        'purchase_number' => 'P-HIST-OTHER',
        'subtotal' => 999,
        'total' => 999,
        'purchased_at' => now(),
    ]);

    $this->actingAs($admin)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Index')
            ->where('historical_summary.periods.0.key', 'last_14_days')
            ->where('historical_summary.periods.0.sales_total', 300)
            ->where('historical_summary.periods.0.purchases_total', 75)
            ->where('historical_summary.periods.0.sales_count', 2)
            ->where('historical_summary.periods.0.average_ticket', 150)
            ->where('historical_summary.periods.1.key', 'current_month')
            ->where('historical_summary.periods.1.sales_total', 300)
            ->where('historical_summary.periods.1.purchases_total', 75)
            ->where('historical_summary.periods.2.key', 'current_year')
            ->where('historical_summary.periods.2.sales_total', 600)
            ->where('historical_summary.periods.2.purchases_total', 105)
            ->where('historical_summary.periods.3.key', 'all_time')
            ->where('historical_summary.periods.3.sales_total', 1000)
            ->where('historical_summary.periods.3.purchases_total', 125)
            ->where('performance_series.periods.0.key', 'last_14_days')
            ->where('performance_series.periods.0.granularity', 'day')
            ->has('performance_series.periods.0.points', 14)
            ->where('performance_series.periods.0.points.13.sales_total', 100)
            ->where('performance_series.periods.0.points.13.purchases_total', 50)
            ->where('performance_series.periods.1.key', 'current_month')
            ->has('performance_series.periods.1.points', 17)
            ->where('performance_series.periods.2.key', 'current_year')
            ->where('performance_series.periods.2.granularity', 'month')
            ->has('performance_series.periods.2.points', 8)
            ->where('performance_series.periods.2.points.7.sales_total', 300)
            ->where('performance_series.periods.3.key', 'all_time')
            ->where('performance_series.periods.3.granularity', 'month')
            ->has('performance_series.periods.3.points', 13)
        );
});

test('dashboard normalizes gram-based top sold products to kilograms', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();

    $weightedProduct = Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Queso',
        'slug' => 'queso-dashboard',
        'unit_type' => 'weight',
        'weight_unit' => 'g',
        'sale_price' => 1800,
        'cost_price' => 1200,
        'stock' => 5000,
        'min_stock' => 500,
        'is_active' => true,
    ]);

    $unitProduct = Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Gaseosa',
        'slug' => 'gaseosa-dashboard',
        'unit_type' => 'unit',
        'sale_price' => 2500,
        'cost_price' => 1500,
        'stock' => 20,
        'min_stock' => 2,
        'is_active' => true,
    ]);

    $sale = Sale::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'sale_number' => 'S-300001',
        'subtotal' => 10000,
        'discount' => 0,
        'total' => 10000,
        'sold_at' => now(),
    ]);

    SaleItem::query()->create([
        'business_id' => $business->id,
        'sale_id' => $sale->id,
        'product_id' => $weightedProduct->id,
        'product_name' => $weightedProduct->name,
        'quantity' => 2500,
        'unit_price' => 1800,
        'subtotal' => 45000,
    ]);

    SaleItem::query()->create([
        'business_id' => $business->id,
        'sale_id' => $sale->id,
        'product_id' => $unitProduct->id,
        'product_name' => $unitProduct->name,
        'quantity' => 8,
        'unit_price' => 2500,
        'subtotal' => 20000,
    ]);

    SaleItem::query()->create([
        'business_id' => $business->id,
        'sale_id' => $sale->id,
        'product_id' => null,
        'product_name' => 'Verdura suelta',
        'quantity' => 999,
        'unit_price' => 100,
        'subtotal' => 99900,
    ]);

    $this->actingAs($admin)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Index')
            ->where('top_sold_products.0.product_name', 'Gaseosa')
            ->where('top_sold_products.0.sold_quantity', 8)
            ->where('top_sold_products.0.sold_quantity_label', 'u')
            ->where('top_sold_products.1.product_name', 'Queso')
            ->where('top_sold_products.1.sold_quantity', 2.5)
            ->where('top_sold_products.1.sold_quantity_label', 'kg')
        );
});

test('dashboard expiration alerts ignore products without available stock', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $supplier = Supplier::query()->create([
        'business_id' => $business->id,
        'name' => 'Proveedor alertas',
    ]);

    $productWithoutStock = Product::query()->create([
        'business_id' => $business->id,
        'supplier_id' => $supplier->id,
        'name' => 'Producto agotado',
        'slug' => 'producto-agotado',
        'unit_type' => 'unit',
        'sale_price' => 100,
        'cost_price' => 50,
        'stock' => 0,
        'min_stock' => 1,
        'expiry_alert_days' => 10,
        'is_active' => true,
    ]);

    $productWithStock = Product::query()->create([
        'business_id' => $business->id,
        'supplier_id' => $supplier->id,
        'name' => 'Producto vigente',
        'slug' => 'producto-vigente',
        'unit_type' => 'unit',
        'sale_price' => 100,
        'cost_price' => 50,
        'stock' => 4,
        'min_stock' => 1,
        'expiry_alert_days' => 10,
        'is_active' => true,
    ]);

    $purchase = Purchase::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'supplier_id' => $supplier->id,
        'purchase_number' => 'P-900001',
        'subtotal' => 100,
        'total' => 100,
        'purchased_at' => now(),
    ]);

    ProductBatch::query()->create([
        'business_id' => $business->id,
        'product_id' => $productWithoutStock->id,
        'batch_code' => 'NO-STOCK',
        'expires_at' => now()->addDays(2)->toDateString(),
        'quantity' => 1,
        'unit_cost' => 50,
    ]);

    ProductBatch::query()->create([
        'business_id' => $business->id,
        'product_id' => $productWithStock->id,
        'batch_code' => 'WITH-STOCK',
        'expires_at' => now()->addDays(2)->toDateString(),
        'quantity' => 1,
        'unit_cost' => 50,
    ]);

    $this->actingAs($admin)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Index')
            ->where('expiration_alerts.0.product_name', 'Producto vigente')
        );
});
