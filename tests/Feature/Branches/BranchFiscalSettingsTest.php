<?php

use App\Models\Branch;
use App\Models\BranchFiscalSetting;
use App\Models\Business;
use App\Models\FiscalIdentity;
use App\Models\Sale;
use App\Models\SaleFiscalDocument;
use App\Models\User;
use App\Services\Fiscal\FiscalQrService;
use App\Services\Fiscal\FiscalSalePayloadBuilder;

test('superadmin can store an independent ARCA configuration for a branch', function () {
    $superAdmin = User::factory()->superadmin()->create();
    $business = Business::factory()->create();
    $branch = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Sucursal Fiscal',
        'code' => 'fiscal',
        'is_active' => true,
        'is_default' => false,
    ]);

    $this->actingAs($superAdmin)
        ->put(route('admin.businesses.branches.fiscal-settings.update', [$business, $branch]), [
            'is_enabled' => false,
            'fiscal_external_business_id' => 'sucursal-fiscal',
            'fiscal_environment' => 'testing',
            'fiscal_cuit' => '',
            'fiscal_condition' => 'monotributo',
            'fiscal_point_of_sale' => 8,
            'fiscal_document_type' => 'invoice_c',
            'fiscal_cbte_type' => 11,
            'fiscal_concept' => 1,
            'fiscal_authorization_mode' => 'cae',
            'fiscal_activities' => '471120, 492140',
        ])
        ->assertRedirect(route('admin.businesses.edit', $business));

    $setting = BranchFiscalSetting::query()->where('branch_id', $branch->id)->firstOrFail();

    expect($setting->business_id)->toBe($business->id)
        ->and($setting->fiscal_identity_id)->toBeNull()
        ->and($setting->fiscal_point_of_sale)->toBe(8)
        ->and($setting->fiscal_activities)->toBeNull();
});

test('branch fiscal point of sale uses the WSFEv1 range', function (): void {
    $superAdmin = User::factory()->superadmin()->create();
    $business = Business::factory()->create();
    $branch = $business->defaultBranch;

    $payload = [
        'is_enabled' => false,
        'fiscal_point_of_sale' => 99998,
    ];

    $this->actingAs($superAdmin)
        ->put(route('admin.businesses.branches.fiscal-settings.update', [$business, $branch]), $payload)
        ->assertRedirect();

    $this->actingAs($superAdmin)
        ->put(route('admin.businesses.branches.fiscal-settings.update', [$business, $branch]), [...$payload, 'fiscal_point_of_sale' => 0])
        ->assertSessionHasErrors('fiscal_point_of_sale');
    $this->actingAs($superAdmin)
        ->put(route('admin.businesses.branches.fiscal-settings.update', [$business, $branch]), [...$payload, 'fiscal_point_of_sale' => 99999])
        ->assertSessionHasErrors('fiscal_point_of_sale');
});

test('nested fiscal identity CUIT errors are returned to the nested field', function (): void {
    $superAdmin = User::factory()->superadmin()->create();
    $business = Business::factory()->create();
    $branch = $business->defaultBranch;

    $this->actingAs($superAdmin)
        ->put(route('admin.businesses.branches.fiscal-settings.update', [$business, $branch]), [
            'is_enabled' => true,
            'fiscal_point_of_sale' => 2,
            'fiscal_identity' => [
                'external_fiscal_id' => 'invalid-cuit-identity',
                'cuit' => '20123456787',
                'environment' => 'testing',
                'fiscal_condition' => 'monotributo',
            ],
        ])
        ->assertSessionHasErrors('fiscal_identity.cuit')
        ->assertSessionDoesntHaveErrors('fiscal_cuit');
});

test('a sale uses the ARCA configuration from its own branch', function () {
    $business = Business::factory()->create([
        'fiscal_enabled' => true,
        'fiscal_external_business_id' => 'comercio-principal',
        'fiscal_cuit' => '20440587780',
        'fiscal_point_of_sale' => 2,
        'fiscal_condition' => 'monotributo',
    ]);
    $user = User::factory()->businessAdmin($business->id)->create();
    $branch = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Sucursal Norte',
        'code' => 'norte',
        'is_active' => true,
        'is_default' => false,
    ]);
    $northIdentity = FiscalIdentity::query()->create([
        'business_id' => $business->id, 'external_fiscal_id' => 'comercio-norte', 'cuit' => '20123456786',
        'environment' => 'testing', 'fiscal_condition' => 'monotributo', 'legal_name' => 'Sucursal Norte',
    ]);
    BranchFiscalSetting::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'fiscal_identity_id' => $northIdentity->id,
        'is_enabled' => true,
        'fiscal_external_business_id' => 'comercio-norte',
        'fiscal_environment' => 'testing',
        'fiscal_cuit' => '20123456786',
        'fiscal_condition' => 'monotributo',
        'fiscal_point_of_sale' => 7,
        'fiscal_document_type' => 'invoice_c',
        'fiscal_cbte_type' => 11,
        'fiscal_concept' => 1,
        'fiscal_authorization_mode' => 'cae',
    ]);
    $sale = Sale::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'sale_number' => 'S-FISCAL-NORTE-001',
        'subtotal' => 100,
        'discount' => 0,
        'total' => 100,
        'sold_at' => now(),
    ]);

    $payload = app(FiscalSalePayloadBuilder::class)->build($sale, 'fiscal-branch-test');

    expect($payload['business_id'])->toBe('comercio-norte')
        ->and($payload['point_of_sale'])->toBe(7);
});

test('fiscal QR uses the CUIT configured for the sale branch', function () {
    $business = Business::factory()->create([
        'fiscal_enabled' => true,
        'fiscal_cuit' => '20440587780',
    ]);
    $user = User::factory()->businessAdmin($business->id)->create();
    $branch = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Sucursal Norte',
        'code' => 'norte',
        'is_active' => true,
        'is_default' => false,
    ]);
    $northIdentity = FiscalIdentity::query()->create([
        'business_id' => $business->id, 'external_fiscal_id' => 'norte-qr', 'cuit' => '20123456786',
        'environment' => 'testing', 'fiscal_condition' => 'monotributo', 'legal_name' => 'Sucursal Norte',
    ]);
    BranchFiscalSetting::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'fiscal_identity_id' => $northIdentity->id,
        'is_enabled' => true,
        'fiscal_cuit' => '20123456786',
        'fiscal_point_of_sale' => 7,
    ]);
    $sale = Sale::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'sale_number' => 'S-FISCAL-QR-001',
        'subtotal' => 100,
        'discount' => 0,
        'total' => 100,
        'sold_at' => now(),
    ]);
    $document = SaleFiscalDocument::query()->create([
        'business_id' => $business->id,
        'sale_id' => $sale->id,
        'attempt_number' => 1,
        'fiscal_status' => SaleFiscalDocument::STATUS_AUTHORIZED,
        'issuer_cuit' => '20123456786',
        'fiscal_point_of_sale' => 7,
        'fiscal_cbte_type' => 11,
        'fiscal_number' => 1,
        'authorization_type' => SaleFiscalDocument::AUTHORIZATION_CAE,
        'authorization_code' => '86173407873027',
        'fiscal_idempotency_key' => 'branch-fiscal-qr-001',
        'fiscal_payload' => [
            'customer' => ['doc_type' => 99, 'doc_number' => 0],
        ],
        'attempted_at' => now(),
        'authorized_at' => now(),
    ]);

    expect(app(FiscalQrService::class)->payload($document)['cuit'])->toBe(20123456786);
});
