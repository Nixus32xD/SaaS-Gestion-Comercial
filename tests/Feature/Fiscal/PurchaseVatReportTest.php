<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleFiscalDocument;
use App\Models\Supplier;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function fiscalPurchasePayload(Product $product, Supplier $supplier, array $fiscal = []): array
{
    return [
        'supplier_id' => $supplier->id,
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 121]],
        'fiscal' => array_replace_recursive([
            'enabled' => true,
            'supplier_cuit' => '30712345671',
            'document_type' => 'invoice_a',
            'point_of_sale' => 3,
            'number' => 12,
            'voucher_date' => '2026-09-03',
            'other_taxes_amount' => 0,
            'total_amount' => 121,
            'items' => [['vat_treatment' => 'gravado', 'vat_rate' => 21, 'net_amount' => 100]],
        ], $fiscal),
    ];
}

function fiscalPurchaseFixture(): array
{
    $business = Business::factory()->create();
    $user = User::factory()->businessAdmin($business->id)->create();
    $supplier = Supplier::query()->create(['business_id' => $business->id, 'name' => 'Proveedor fiscal']);
    $product = Product::query()->create([
        'business_id' => $business->id, 'name' => 'Producto fiscal', 'slug' => 'producto-fiscal-'.$business->id,
        'unit_type' => 'unit', 'sale_price' => 200, 'cost_price' => 100, 'stock' => 0, 'min_stock' => 0, 'is_active' => true,
    ]);

    return [$business, $user, $supplier, $product];
}

test('purchase stores a fiscal voucher and IVA credit by aliquot', function () {
    [$business, $user, $supplier, $product] = fiscalPurchaseFixture();

    $this->actingAs($user)->post('/purchases', fiscalPurchasePayload($product, $supplier, [
        'items' => [
            ['vat_treatment' => 'gravado', 'vat_rate' => 21, 'net_amount' => 100],
            ['vat_treatment' => 'gravado', 'vat_rate' => 10.5, 'net_amount' => 200],
        ],
        'total_amount' => 342,
    ]))->assertRedirect();

    $purchase = Purchase::query()->firstOrFail();
    expect($purchase->supplier_cuit)->toBe('30712345671')
        ->and((float) $purchase->fiscal_net_amount)->toBe(300.0)
        ->and((float) $purchase->fiscal_vat_amount)->toBe(42.0)
        ->and((float) $purchase->fiscal_total_amount)->toBe(342.0)
        ->and($purchase->fiscalItems)->toHaveCount(2);
});

test('purchase without fiscal data remains compatible', function () {
    [$business, $user, $supplier, $product] = fiscalPurchaseFixture();

    $this->actingAs($user)->post('/purchases', [
        'supplier_id' => $supplier->id,
        'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_cost' => 50]],
    ])->assertRedirect();

    $purchase = Purchase::query()->firstOrFail();
    expect($purchase->fiscal_document_type)->toBeNull()
        ->and($purchase->fiscalItems)->toHaveCount(0)
        ->and((float) $purchase->total)->toBe(100.0);
});

test('fiscal purchase rejects invalid totals and invalid cuit', function () {
    [, $user, $supplier, $product] = fiscalPurchaseFixture();

    $this->actingAs($user)->from('/purchases/create')->post('/purchases', fiscalPurchasePayload($product, $supplier, [
        'total_amount' => 120,
    ]))->assertSessionHasErrors('fiscal.total_amount');

    $this->actingAs($user)->from('/purchases/create')->post('/purchases', fiscalPurchasePayload($product, $supplier, [
        'supplier_cuit' => '30123456789',
    ]))->assertSessionHasErrors('fiscal.supplier_cuit');
});

test('monthly IVA report compares authorized sales and fiscal purchases by branch', function () {
    $this->withoutVite();
    [$business, $user, $supplier, $product] = fiscalPurchaseFixture();
    $branch = $business->defaultBranch;
    $otherBranch = Branch::query()->create(['business_id' => $business->id, 'name' => 'Sucursal Sur', 'code' => 'sur', 'is_active' => true, 'is_default' => false]);

    $sale = Sale::query()->create([
        'business_id' => $business->id, 'branch_id' => $branch->id, 'user_id' => $user->id, 'sale_number' => 'S-FISCAL-1',
        'payment_status' => 'paid', 'subtotal' => 1210, 'total' => 1210, 'fiscal_net_amount' => 1000, 'fiscal_vat_amount' => 210,
        'fiscal_exempt_amount' => 0, 'fiscal_non_taxed_amount' => 0, 'sold_at' => '2026-09-10 12:00:00',
    ]);
    SaleFiscalDocument::query()->create([
        'business_id' => $business->id, 'sale_id' => $sale->id, 'attempt_number' => 1,
        'fiscal_status' => SaleFiscalDocument::STATUS_AUTHORIZED,
        'fiscal_idempotency_key' => "sale:{$business->id}:{$sale->id}:fiscal-report",
        'attempted_at' => now(),
    ]);

    $this->actingAs($user)->post('/purchases', fiscalPurchasePayload($product, $supplier))->assertRedirect();
    Purchase::query()->forBusiness($business->id)->create([
        'business_id' => $business->id,
        'branch_id' => $otherBranch->id, 'user_id' => $user->id, 'purchase_number' => 'P-OTHER', 'subtotal' => 0, 'total' => 0,
        'supplier_cuit' => '20440587780', 'fiscal_document_type' => 'invoice_a', 'fiscal_point_of_sale' => 1, 'fiscal_number' => 1,
        'fiscal_voucher_date' => '2026-09-05', 'fiscal_net_amount' => 100, 'fiscal_vat_amount' => 21, 'fiscal_exempt_amount' => 0,
        'fiscal_non_taxed_amount' => 0, 'fiscal_other_taxes_amount' => 0, 'fiscal_total_amount' => 121, 'purchased_at' => '2026-09-05 10:00:00',
    ]);

    $this->actingAs($user)->get('/fiscal/iva?month=2026-09')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Fiscal/VatDashboard')
            ->where('report.sales.vat_amount', 210)
            ->where('report.purchases.vat_amount', 21)
            ->where('report.estimated_difference', 189)
            ->has('report.purchase_records', 1)
        );

    $this->actingAs($user)->get('/fiscal/iva?month=2026-09&branch_scope=all')
        ->assertInertia(fn (Assert $page) => $page->where('report.purchases.vat_amount', 42));

    $this->actingAs($user)->get('/fiscal/iva?month=2026-09&export=csv')
        ->assertOk()
        ->assertDownload('iva-compras-2026-09.csv');
});

test('fiscal monthly report excludes another business', function () {
    $this->withoutVite();
    [$business, $user, $supplier, $product] = fiscalPurchaseFixture();
    [$otherBusiness, $otherUser, $otherSupplier, $otherProduct] = fiscalPurchaseFixture();

    $this->actingAs($otherUser)->post('/purchases', fiscalPurchasePayload($otherProduct, $otherSupplier))->assertRedirect();

    $this->actingAs($user)->get('/fiscal/iva?month=2026-09')
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.purchases.vat_amount', 0)
            ->has('report.purchase_records', 0)
        );
});
