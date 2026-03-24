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
