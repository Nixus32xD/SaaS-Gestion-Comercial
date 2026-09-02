<?php

use App\Models\Branch;
use App\Models\BranchFiscalSetting;
use App\Models\Business;
use App\Models\FiscalIdentity;
use App\Models\Sale;
use App\Models\SaleFiscalDocument;
use App\Models\User;
use App\Services\Fiscal\FiscalPointOfSaleOptionsService;
use App\Services\Fiscal\FiscalQrService;
use App\Services\Fiscal\FiscalSalePayloadBuilder;
use Illuminate\Support\Facades\Http;

function fiscalIdentityArchitectureSale(Business $business, Branch $branch, User $user, string $number): Sale
{
    return Sale::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'sale_number' => $number,
        'subtotal' => 100,
        'discount' => 0,
        'total' => 100,
        'sold_at' => now(),
    ]);
}

test('each branch payload uses its fiscal identity and its own point of sale', function (): void {
    $business = Business::factory()->create(['fiscal_enabled' => true]);
    $user = User::factory()->businessAdmin($business->id)->create();
    $branch1 = $business->defaultBranch;
    $branch2 = Branch::query()->create(['business_id' => $business->id, 'name' => 'Sucursal 2', 'code' => 's2', 'is_active' => true]);
    $branch3 = Branch::query()->create(['business_id' => $business->id, 'name' => 'Sucursal 3', 'code' => 's3', 'is_active' => true]);
    $identityA = FiscalIdentity::query()->create(['business_id' => $business->id, 'external_fiscal_id' => 'fiscal-nicolas', 'cuit' => '20123456786', 'environment' => 'production', 'fiscal_condition' => 'monotributo', 'legal_name' => 'Nicolás Morón']);
    $identityB = FiscalIdentity::query()->create(['business_id' => $business->id, 'external_fiscal_id' => 'fiscal-hermano', 'cuit' => '20440587780', 'environment' => 'production', 'fiscal_condition' => 'monotributo', 'legal_name' => 'Hermano']);

    foreach ([[$branch1, $identityA, 5], [$branch2, $identityB, 3], [$branch3, $identityA, 8]] as [$branch, $identity, $pointOfSale]) {
        BranchFiscalSetting::query()->create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'fiscal_identity_id' => $identity->id,
            'is_enabled' => true, 'fiscal_point_of_sale' => $pointOfSale, 'fiscal_document_type' => 'invoice_c',
            'fiscal_cbte_type' => 11, 'fiscal_concept' => 1, 'fiscal_authorization_mode' => 'cae',
        ]);
    }

    $builder = app(FiscalSalePayloadBuilder::class);
    $payload1 = $builder->build(fiscalIdentityArchitectureSale($business, $branch1, $user, 'S-1'), 'identity-1');
    $payload2 = $builder->build(fiscalIdentityArchitectureSale($business, $branch2, $user, 'S-2'), 'identity-2');
    $payload3 = $builder->build(fiscalIdentityArchitectureSale($business, $branch3, $user, 'S-3'), 'identity-3');

    expect($payload1['external_fiscal_id'])->toBe('fiscal-nicolas')->and($payload1['point_of_sale'])->toBe(5)
        ->and($payload2['external_fiscal_id'])->toBe('fiscal-hermano')->and($payload2['point_of_sale'])->toBe(3)
        ->and($payload3['external_fiscal_id'])->toBe('fiscal-nicolas')->and($payload3['point_of_sale'])->toBe(8)
        ->and($identityA->cuit)->toBe('20123456786')->and($identityB->cuit)->toBe('20440587780');
});

test('authorized documents retain their issuer snapshot when a branch changes identity', function (): void {
    $business = Business::factory()->create();
    $user = User::factory()->businessAdmin($business->id)->create();
    $branch = $business->defaultBranch;
    $identityA = FiscalIdentity::query()->create(['business_id' => $business->id, 'external_fiscal_id' => 'identity-a', 'cuit' => '20123456786', 'environment' => 'production', 'fiscal_condition' => 'monotributo', 'legal_name' => 'Emisor A']);
    $identityB = FiscalIdentity::query()->create(['business_id' => $business->id, 'external_fiscal_id' => 'identity-b', 'cuit' => '20440587780', 'environment' => 'production', 'fiscal_condition' => 'monotributo', 'legal_name' => 'Emisor B']);
    $setting = BranchFiscalSetting::query()->create(['business_id' => $business->id, 'branch_id' => $branch->id, 'fiscal_identity_id' => $identityA->id, 'is_enabled' => true, 'fiscal_point_of_sale' => 5]);
    $sale = fiscalIdentityArchitectureSale($business, $branch, $user, 'S-SNAPSHOT');
    $document = SaleFiscalDocument::query()->create([
        'business_id' => $business->id, 'sale_id' => $sale->id, 'fiscal_identity_id' => $identityA->id,
        'attempt_number' => 1, 'fiscal_status' => SaleFiscalDocument::STATUS_AUTHORIZED,
        'fiscal_external_id' => 'identity-a', 'issuer_cuit' => '20123456786', 'issuer_legal_name' => 'Emisor A',
        'issuer_fiscal_condition' => 'monotributo', 'fiscal_environment' => 'production',
        'fiscal_point_of_sale' => 5, 'fiscal_cbte_type' => 11, 'fiscal_number' => 1,
        'authorization_type' => 'CAE', 'authorization_code' => '86173407873027', 'fiscal_idempotency_key' => 'snapshot-1',
        'fiscal_payload' => ['customer' => ['doc_type' => 99, 'doc_number' => 0]], 'authorized_at' => now(),
    ]);

    $setting->update(['fiscal_identity_id' => $identityB->id, 'fiscal_point_of_sale' => 8]);

    expect(app(FiscalQrService::class)->issuerCuit($document))->toBe('20123456786')
        ->and($document->issuer_legal_name)->toBe('Emisor A')
        ->and($document->fiscal_point_of_sale)->toBe(5);
});

test('points of sale are queried for the selected fiscal identity', function (): void {
    config(['fiscal.enabled' => true, 'fiscal.base_url' => 'https://arca.test', 'fiscal.token' => 'test-token']);
    $business = Business::factory()->create();
    $identity = FiscalIdentity::query()->create(['business_id' => $business->id, 'external_fiscal_id' => 'identity-points', 'cuit' => '20123456786', 'environment' => 'testing', 'fiscal_condition' => 'monotributo']);
    Http::fake(['https://arca.test/fiscal/companies/identity-points/points-of-sale' => Http::response(['data' => ['points_of_sale' => [['number' => 8, 'type' => 'CAE', 'blocked' => false]]]])]);

    $result = app(FiscalPointOfSaleOptionsService::class)->forIdentity($identity);

    expect($result['status'])->toBe('ok')->and($result['options'][0]['value'])->toBe(8);
});

test('legacy backfill groups matching branch settings and stops before schema changes on conflicts', function (): void {
    $migration = require database_path('migrations/2026_09_01_000002_normalize_fiscal_identities.php');
    $migration->down();
    $business = Business::factory()->create();
    $branch2 = Branch::query()->create(['business_id' => $business->id, 'name' => 'Otra', 'code' => 'otra', 'is_active' => true]);
    foreach ([$business->defaultBranch, $branch2] as $branch) {
        BranchFiscalSetting::query()->create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'is_enabled' => true,
            'fiscal_external_business_id' => 'legacy-identity', 'fiscal_cuit' => '20123456786', 'fiscal_environment' => 'testing',
            'fiscal_condition' => 'monotributo', 'fiscal_point_of_sale' => $branch->id === $branch2->id ? 8 : 5,
        ]);
    }
    $migration->up();

    expect(FiscalIdentity::query()->where('external_fiscal_id', 'legacy-identity')->count())->toBe(1)
        ->and(BranchFiscalSetting::query()->whereNotNull('fiscal_identity_id')->count())->toBe(2);
});

test('legacy backfill refuses a shared external ID with conflicting CUITs', function (): void {
    $migration = require database_path('migrations/2026_09_01_000002_normalize_fiscal_identities.php');
    $migration->down();
    $business = Business::factory()->create();
    $branch2 = Branch::query()->create(['business_id' => $business->id, 'name' => 'Conflicto', 'code' => 'conflicto', 'is_active' => true]);
    foreach ([[$business->defaultBranch, '20123456786'], [$branch2, '20440587780']] as [$branch, $cuit]) {
        BranchFiscalSetting::query()->create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'is_enabled' => true,
            'fiscal_external_business_id' => 'shared-conflict', 'fiscal_cuit' => $cuit,
            'fiscal_environment' => 'testing', 'fiscal_condition' => 'monotributo', 'fiscal_point_of_sale' => 2,
        ]);
    }

    expect(fn () => $migration->up())->toThrow(RuntimeException::class)
        ->and(\Illuminate\Support\Facades\Schema::hasTable('fiscal_identities'))->toBeFalse();
});
