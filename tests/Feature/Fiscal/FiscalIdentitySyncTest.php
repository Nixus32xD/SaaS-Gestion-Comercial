<?php

use App\Models\BranchFiscalSetting;
use App\Models\Business;
use App\Models\FiscalIdentity;
use App\Models\Sale;
use App\Models\User;
use App\Services\Fiscal\BranchFiscalSettingsResolver;
use App\Services\Fiscal\FiscalIdentityService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

function fiscalIdentityAttributes(string $externalFiscalId = 'identity-sync'): array
{
    return [
        'external_fiscal_id' => $externalFiscalId,
        'cuit' => '20123456786',
        'environment' => 'testing',
        'fiscal_condition' => 'monotributo',
        'legal_name' => 'Identidad de prueba',
        'fiscal_activities' => [471120],
    ];
}

beforeEach(function (): void {
    config([
        'fiscal.enabled' => true,
        'fiscal.base_url' => 'https://arca.test',
        'fiscal.token' => 'test-token',
    ]);
});

test('persists and marks a fiscal identity as synced after apiArca accepts it', function (): void {
    Http::fake(['*' => Http::response(['data' => ['id' => 1]], 201)]);
    $business = Business::factory()->create();

    $identity = app(FiscalIdentityService::class)->create($business, fiscalIdentityAttributes());

    expect($identity->sync_status)->toBe(FiscalIdentity::SYNC_SYNCED)
        ->and($identity->synced_at)->not->toBeNull()
        ->and(FiscalIdentity::query()->count())->toBe(1);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://arca.test/fiscal/companies'
        && $request['external_fiscal_id'] === 'identity-sync');
});

test('keeps a recoverable failed identity when apiArca times out and retries the same row', function (): void {
    $attempt = 0;
    Http::fake(function () use (&$attempt) {
        if ($attempt++ === 0) {
            throw new ConnectionException('timeout');
        }

        return Http::response(['data' => ['id' => 1]], 201);
    });
    $business = Business::factory()->create();

    expect(fn () => app(FiscalIdentityService::class)->create($business, fiscalIdentityAttributes()))
        ->toThrow(ValidationException::class);

    $failed = FiscalIdentity::query()->sole();
    expect($failed->sync_status)->toBe(FiscalIdentity::SYNC_FAILED)
        ->and($failed->sync_error)->not->toBeNull();

    $retried = app(FiscalIdentityService::class)->create($business, fiscalIdentityAttributes());

    expect($retried->id)->toBe($failed->id)
        ->and($retried->sync_status)->toBe(FiscalIdentity::SYNC_SYNCED)
        ->and(FiscalIdentity::query()->count())->toBe(1);
});

test('a duplicate creation resolves to the single existing fiscal identity', function (): void {
    Http::fake(['https://arca.test/fiscal/companies' => Http::response(['data' => ['id' => 1]], 201)]);
    $business = Business::factory()->create();
    $service = app(FiscalIdentityService::class);

    $first = $service->create($business, fiscalIdentityAttributes());
    $second = $service->create($business, fiscalIdentityAttributes());

    expect($second->id)->toBe($first->id)
        ->and(FiscalIdentity::query()->where('external_fiscal_id', 'identity-sync')->count())->toBe(1);
});

test('a CUIT and environment cannot be reused for a different external fiscal identity', function (): void {
    Http::fake(['https://arca.test/fiscal/companies' => Http::response(['data' => ['id' => 1]], 201)]);
    $business = Business::factory()->create();
    $service = app(FiscalIdentityService::class);
    $service->create($business, fiscalIdentityAttributes('identity-a'));

    expect(fn () => $service->create($business, fiscalIdentityAttributes('identity-b')))
        ->toThrow(ValidationException::class);

    expect(FiscalIdentity::query()->count())->toBe(1);
});

test('a non-synced identity cannot enable fiscal issuance for a branch', function (): void {
    $business = Business::factory()->create();
    $identity = FiscalIdentity::query()->create([
        'business_id' => $business->id,
        ...fiscalIdentityAttributes('identity-failed'),
        'sync_status' => FiscalIdentity::SYNC_FAILED,
    ]);
    $branch = $business->defaultBranch;
    BranchFiscalSetting::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'fiscal_identity_id' => $identity->id,
        'is_enabled' => true,
        'fiscal_point_of_sale' => 2,
    ]);
    $sale = Sale::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'user_id' => User::factory()->businessAdmin($business->id)->create()->id,
        'sale_number' => 'IDENTITY-SYNC-1',
        'subtotal' => 100,
        'discount' => 0,
        'total' => 100,
        'sold_at' => now(),
    ]);

    $resolver = app(BranchFiscalSettingsResolver::class);

    expect($resolver->isEnabledForBranch($business, $branch))->toBeFalse()
        ->and(fn () => $resolver->identityForSale($sale))->toThrow(ValidationException::class);
});
