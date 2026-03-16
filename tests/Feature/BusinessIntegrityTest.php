<?php

use App\Models\Business;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\QueryException;

test('sale items can not reference a product from another business', function () {
    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();
    $adminA = User::factory()->businessAdmin($businessA->id)->create();

    $sale = Sale::query()->create([
        'business_id' => $businessA->id,
        'user_id' => $adminA->id,
        'sale_number' => 'S-900001',
        'subtotal' => 100,
        'discount' => 0,
        'total' => 100,
        'sold_at' => now(),
    ]);

    $foreignProduct = Product::query()->create([
        'business_id' => $businessB->id,
        'name' => 'Producto ajeno',
        'slug' => 'producto-ajeno-integrity',
        'unit_type' => 'unit',
        'sale_price' => 100,
        'cost_price' => 50,
        'stock' => 10,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    expect(fn () => SaleItem::query()->create([
        'business_id' => $businessA->id,
        'sale_id' => $sale->id,
        'product_id' => $foreignProduct->id,
        'product_name' => $foreignProduct->name,
        'quantity' => 1,
        'unit_price' => 100,
        'subtotal' => 100,
    ]))->toThrow(QueryException::class);
});

test('purchase items can not reference a product from another business', function () {
    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();
    $adminA = User::factory()->businessAdmin($businessA->id)->create();
    $supplier = Supplier::query()->create([
        'business_id' => $businessA->id,
        'name' => 'Proveedor integridad',
    ]);

    $purchase = Purchase::query()->create([
        'business_id' => $businessA->id,
        'user_id' => $adminA->id,
        'supplier_id' => $supplier->id,
        'purchase_number' => 'P-900001',
        'subtotal' => 100,
        'total' => 100,
        'purchased_at' => now(),
    ]);

    $foreignProduct = Product::query()->create([
        'business_id' => $businessB->id,
        'name' => 'Producto ajeno compra',
        'slug' => 'producto-ajeno-compra-integrity',
        'unit_type' => 'unit',
        'sale_price' => 100,
        'cost_price' => 50,
        'stock' => 10,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    expect(fn () => PurchaseItem::query()->create([
        'business_id' => $businessA->id,
        'purchase_id' => $purchase->id,
        'product_id' => $foreignProduct->id,
        'product_name' => $foreignProduct->name,
        'quantity' => 1,
        'unit_cost' => 100,
        'subtotal' => 100,
    ]))->toThrow(QueryException::class);
});
