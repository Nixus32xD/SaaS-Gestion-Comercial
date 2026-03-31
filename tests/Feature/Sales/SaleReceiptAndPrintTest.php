<?php

use App\Models\Business;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function saleProductFor(Business $business): Product
{
    return Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Producto venta '.$business->id,
        'slug' => 'producto-venta-'.$business->id.'-'.fake()->unique()->slug(),
        'unit_type' => 'unit',
        'sale_price' => 2500,
        'cost_price' => 1400,
        'stock' => 20,
        'min_stock' => 2,
        'is_active' => true,
    ]);
}

function simpleSaleFor(Business $business, User $user, string $saleNumber): Sale
{
    return Sale::query()->create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'sale_number' => $saleNumber,
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'amount_received' => 2500,
        'change_amount' => 0,
        'paid_amount' => 2500,
        'pending_amount' => 0,
        'subtotal' => 2500,
        'discount' => 0,
        'total' => 2500,
        'sold_at' => now(),
    ]);
}

test('sale can store a receipt when created', function () {
    Storage::fake('local');

    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $product = saleProductFor($business);
    $receipt = UploadedFile::fake()->create('ticket.pdf', 120, 'application/pdf');

    $this->actingAs($admin)
        ->post('/sales', [
            'payment_method' => 'cash',
            'amount_received' => 3000,
            'receipt' => $receipt,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 2500,
            ]],
        ])
        ->assertRedirect();

    $sale = Sale::query()->firstOrFail();

    expect($sale->receipt_path)->not()->toBeNull();
    expect($sale->receipt_original_name)->toBe('ticket.pdf');
    expect($sale->receipt_uploaded_at)->not()->toBeNull();

    Storage::disk('local')->assertExists($sale->receipt_path);
});

test('sale receipt can be uploaded and downloaded only by the owning business', function () {
    Storage::fake('local');

    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();
    $adminA = User::factory()->businessAdmin($businessA->id)->create();
    $adminB = User::factory()->businessAdmin($businessB->id)->create();
    $sale = simpleSaleFor($businessA, $adminA, 'S-880001');

    $this->actingAs($adminA)
        ->post(route('sales.receipt.store', $sale), [
            'receipt' => UploadedFile::fake()->image('transferencia.png'),
        ])
        ->assertRedirect(route('sales.show', $sale));

    $sale->refresh();

    expect($sale->receipt_path)->not()->toBeNull();
    expect($sale->receipt_original_name)->toBe('transferencia.png');

    Storage::disk('local')->assertExists($sale->receipt_path);

    $this->actingAs($adminA)
        ->get(route('sales.receipt.download', $sale))
        ->assertOk();

    $this->actingAs($adminB)
        ->get(route('sales.receipt.download', $sale))
        ->assertForbidden();

    $this->actingAs($adminB)
        ->post(route('sales.receipt.store', $sale), [
            'receipt' => UploadedFile::fake()->create('otro.pdf', 90, 'application/pdf'),
        ])
        ->assertForbidden();
});

test('sales print routes only expose records from the active business', function () {
    $businessA = Business::factory()->create(['name' => 'Comercio A']);
    $businessB = Business::factory()->create(['name' => 'Comercio B']);
    $adminA = User::factory()->businessAdmin($businessA->id)->create();
    $adminB = User::factory()->businessAdmin($businessB->id)->create();

    $saleA = simpleSaleFor($businessA, $adminA, 'S-A-0001');
    $foreignSale = simpleSaleFor($businessB, $adminB, 'S-B-0001');

    $this->actingAs($adminA)
        ->get(route('sales.print.index'))
        ->assertOk()
        ->assertSee('S-A-0001')
        ->assertDontSee('S-B-0001');

    $this->actingAs($adminA)
        ->get(route('sales.print.show', $saleA))
        ->assertOk()
        ->assertSee('S-A-0001');

    $this->actingAs($adminA)
        ->get(route('sales.print.show', $foreignSale))
        ->assertForbidden();
});

test('sales pages render receipt tools when receipts are available', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $sale = simpleSaleFor($business, $admin, 'S-990001');

    $this->actingAs($admin)
        ->get(route('sales.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Index')
            ->where('receipt_feature_available', true)
            ->where('sales.data.0.sale_number', 'S-990001')
        );

    $this->actingAs($admin)
        ->get(route('sales.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Create')
            ->where('receipt_feature_available', true)
        );

    $this->actingAs($admin)
        ->get(route('sales.show', $sale))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Show')
            ->where('receipt_feature_available', true)
            ->where('sale.sale_number', 'S-990001')
        );
});
