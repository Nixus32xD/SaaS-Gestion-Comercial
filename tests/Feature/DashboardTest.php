<?php

use App\Models\Business;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
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
