<?php

use App\Models\Branch;
use App\Models\BranchFiscalSetting;
use App\Models\BranchMercadoPagoPointSetting;
use App\Models\Business;
use App\Models\BusinessFeature;
use App\Models\BusinessMercadoPagoCredential;
use App\Models\Sale;
use App\Models\SaleFiscalDocument;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('ARCA module is hidden and blocked when the active branch is disabled', function () {
    $this->withoutVite();

    config()->set('fiscal.enabled', true);
    $business = Business::factory()->create(['fiscal_enabled' => true]);
    $admin = User::factory()->businessAdmin($business->id)->create();
    $branch = $business->defaultBranch;
    BranchFiscalSetting::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'is_enabled' => false,
    ]);

    $this->actingAs($admin)
        ->withSession(['branch_id' => $branch->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('modules.electronic_billing.enabled', false)
        );

    $this->actingAs($admin)
        ->withSession(['branch_id' => $branch->id])
        ->get(route('electronic-billing.index'))
        ->assertForbidden();
});

test('ARCA screen uses the active branch profile and only its fiscal documents', function () {
    $this->withoutVite();

    config()->set('fiscal.enabled', true);
    config()->set('fiscal.base_url', 'http://127.0.0.1:8000/api');
    config()->set('fiscal.token', 'testing-token');
    $business = Business::factory()->create(['fiscal_enabled' => true]);
    $admin = User::factory()->businessAdmin($business->id)->create();
    $branch = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Sucursal Norte',
        'code' => 'norte',
        'is_active' => true,
        'is_default' => false,
    ]);
    BranchFiscalSetting::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'is_enabled' => true,
        'fiscal_external_business_id' => 'empresa-norte',
        'fiscal_cuit' => '20123456786',
        'fiscal_condition' => 'monotributo',
        'fiscal_point_of_sale' => 8,
    ]);
    $northSale = Sale::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'user_id' => $admin->id,
        'sale_number' => 'S-NORTE-001',
        'subtotal' => 100,
        'discount' => 0,
        'total' => 100,
        'sold_at' => now(),
    ]);
    $otherSale = Sale::query()->create([
        'business_id' => $business->id,
        'branch_id' => $business->defaultBranch->id,
        'user_id' => $admin->id,
        'sale_number' => 'S-PRINCIPAL-001',
        'subtotal' => 100,
        'discount' => 0,
        'total' => 100,
        'sold_at' => now(),
    ]);
    foreach ([$northSale, $otherSale] as $index => $sale) {
        SaleFiscalDocument::query()->create([
            'business_id' => $business->id,
            'sale_id' => $sale->id,
            'attempt_number' => 1,
            'fiscal_status' => SaleFiscalDocument::STATUS_AUTHORIZED,
            'fiscal_point_of_sale' => 8,
            'fiscal_idempotency_key' => "branch-test-{$index}",
            'attempted_at' => now(),
        ]);
    }

    \Illuminate\Support\Facades\Http::fake([
        'http://127.0.0.1:8000/api/fiscal/companies/empresa-norte/*' => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $this->actingAs($admin)
        ->withSession(['branch_id' => $branch->id])
        ->get(route('electronic-billing.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Fiscal/Index')
            ->where('configuration.branch_name', 'Sucursal Norte')
            ->where('configuration.external_business_id', 'empresa-norte')
            ->where('configuration.point_of_sale', 8)
            ->where('documents.0.sale_number', 'S-NORTE-001')
            ->has('documents', 1)
        );
});

test('POS only enables Mercado Pago Point when the active branch has an enabled terminal', function () {
    $this->withoutVite();

    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $branch = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Sucursal Sur',
        'code' => 'sur',
        'is_active' => true,
        'is_default' => false,
    ]);
    BusinessMercadoPagoCredential::query()->create([
        'business_id' => $business->id,
        'is_enabled' => true,
        'environment' => 'testing',
        'access_token' => 'TEST-ACCESS-TOKEN',
        'point_terminal_id' => 'TERMINAL-COMERCIO',
    ]);
    BranchMercadoPagoPointSetting::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'is_enabled' => false,
    ]);

    $this->actingAs($admin)
        ->withSession(['branch_id' => $branch->id])
        ->get(route('sales.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('mercadopago_point.enabled', false)
            ->where('mercadopago_point.terminal_configured', false)
        );
});

test('dashboard hides advanced sales for a branch where they are disabled', function () {
    $this->withoutVite();

    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $branch = $business->defaultBranch;
    BusinessFeature::query()->create([
        'business_id' => $business->id,
        'feature' => BusinessFeature::ADVANCED_SALE_SETTINGS,
        'is_enabled' => true,
    ]);
    $branch->commercialSetting()->create([
        'business_id' => $business->id,
        'advanced_sale_settings_enabled' => false,
    ]);

    $this->actingAs($admin)
        ->withSession(['branch_id' => $branch->id])
        ->get(route('dashboard', ['branch_scope' => 'current']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('advanced_sales.enabled', false)
        );
});
