<?php

use App\Models\Business;
use App\Models\BusinessPaymentDestination;
use App\Models\BusinessSaleSector;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

test('superadmin can configure advanced sale settings for a business', function () {
    $superAdmin = User::factory()->superadmin()->create();
    $business = Business::factory()->create();

    $this->actingAs($superAdmin)
        ->put(route('admin.businesses.sales-settings.update', $business), [
            'advanced_sale_settings_enabled' => true,
            'global_product_catalog_enabled' => true,
            'sale_sectors' => [
                ['name' => 'Almacen', 'description' => 'Mostrador principal', 'is_active' => true],
                ['name' => 'Viviendas', 'description' => 'Ventas por unidad', 'is_active' => true],
            ],
            'payment_destinations' => [
                [
                    'name' => 'Mercado Pago Almacen',
                    'account_holder' => 'Comercio SA',
                    'reference' => 'alias.almacen',
                    'account_number' => 'CVU-001',
                    'is_active' => true,
                ],
                [
                    'name' => 'Banco Viviendas',
                    'account_holder' => 'Comercio SA',
                    'reference' => 'CBU viviendas',
                    'account_number' => 'CBU-002',
                    'is_active' => true,
                ],
            ],
        ])
        ->assertRedirect(route('admin.businesses.edit', $business));

    expect($business->fresh()->hasAdvancedSaleSettings())->toBeTrue();
    expect($business->fresh()->hasGlobalProductCatalog())->toBeTrue();

    expect(BusinessSaleSector::query()->where('business_id', $business->id)->orderBy('sort_order')->pluck('name')->all())
        ->toBe(['Almacen', 'Viviendas']);

    expect(BusinessPaymentDestination::query()->where('business_id', $business->id)->orderBy('sort_order')->pluck('name')->all())
        ->toBe(['Mercado Pago Almacen', 'Banco Viviendas']);
});

test('superadmin can configure fiscal cuit for a business', function () {
    $superAdmin = User::factory()->superadmin()->create();
    $business = Business::factory()->create();

    $this->actingAs($superAdmin)
        ->put(route('admin.businesses.sales-settings.update', $business), [
            'fiscal_enabled' => true,
            'fiscal_external_business_id' => 'empresa-demo-prod',
            'fiscal_environment' => 'production',
            'fiscal_cuit' => '30-71234567-1',
            'fiscal_point_of_sale' => 2,
            'fiscal_document_type' => 'invoice_c',
            'fiscal_cbte_type' => 11,
            'fiscal_concept' => 1,
            'fiscal_authorization_mode' => 'caea',
            'fiscal_activities' => '492140',
        ])
        ->assertRedirect(route('admin.businesses.edit', $business));

    $business->refresh();

    expect($business->fiscal_enabled)->toBeTrue();
    expect($business->fiscal_external_business_id)->toBe('empresa-demo-prod');
    expect($business->fiscal_environment)->toBe('production');
    expect($business->fiscal_cuit)->toBe('30712345671');
    expect($business->fiscal_authorization_mode)->toBe('caea');
    expect($business->fiscal_activities)->toBe([492140]);
});

test('enabling fiscal billing syncs the external fiscal company', function () {
    config()->set('fiscal.enabled', true);
    config()->set('fiscal.base_url', 'http://127.0.0.1:8000/api');
    config()->set('fiscal.token', 'testing-fiscal-token');
    config()->set('fiscal.environment', 'local');

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/companies' => Http::response([
            'data' => [
                'business_id' => 'empresa-demo-prod',
            ],
        ], 201),
    ]);

    $superAdmin = User::factory()->superadmin()->create();
    $business = Business::factory()->create([
        'name' => 'Empresa Demo SA',
        'slug' => 'empresa-demo',
    ]);

    $this->actingAs($superAdmin)
        ->put(route('admin.businesses.sales-settings.update', $business), [
            'fiscal_enabled' => true,
            'fiscal_external_business_id' => 'empresa-demo-prod',
            'fiscal_environment' => 'production',
            'fiscal_cuit' => '30-71234567-1',
            'fiscal_point_of_sale' => 2,
            'fiscal_document_type' => 'invoice_c',
            'fiscal_cbte_type' => 11,
            'fiscal_concept' => 1,
            'fiscal_authorization_mode' => 'cae',
            'fiscal_activities' => '492140',
        ])
        ->assertRedirect(route('admin.businesses.edit', $business));

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request): bool {
        $payload = $request->data();

        return $request->method() === 'POST'
            && $request->url() === 'http://127.0.0.1:8000/api/fiscal/companies'
            && $request->hasHeader('Authorization', 'Bearer testing-fiscal-token')
            && $payload['external_business_id'] === 'empresa-demo-prod'
            && $payload['cuit'] === '30712345671'
            && $payload['legal_name'] === 'Empresa Demo SA'
            && $payload['environment'] === 'production'
            && $payload['default_point_of_sale'] === 2
            && $payload['default_voucher_type'] === 11
            && $payload['enabled'] === true
            && $payload['onboarding_metadata']['business_slug'] === 'empresa-demo'
            && $payload['onboarding_metadata']['authorization_mode'] === 'cae'
            && $payload['onboarding_metadata']['activities'] === [492140];
    });

    $business->refresh();

    expect($business->fiscal_enabled)->toBeTrue();
});

test('fiscal billing settings are not saved when external company sync fails', function () {
    config()->set('fiscal.enabled', true);
    config()->set('fiscal.base_url', 'http://127.0.0.1:8000/api');
    config()->set('fiscal.token', 'testing-fiscal-token');

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/companies' => Http::response([
            'message' => 'Could not persist fiscal company.',
            'error_code' => 'company_persist_failed',
        ], 422),
    ]);

    $superAdmin = User::factory()->superadmin()->create();
    $business = Business::factory()->create([
        'fiscal_enabled' => false,
    ]);

    $this->actingAs($superAdmin)
        ->from(route('admin.businesses.edit', $business))
        ->put(route('admin.businesses.sales-settings.update', $business), [
            'fiscal_enabled' => true,
            'fiscal_external_business_id' => 'empresa-demo-prod',
            'fiscal_cuit' => '30-71234567-1',
        ])
        ->assertRedirect(route('admin.businesses.edit', $business))
        ->assertSessionHasErrors('fiscal_enabled');

    expect($business->fresh()->fiscal_enabled)->toBeFalse();
});

test('business edit exposes fiscal selects and point of sale options from fiscal api', function () {
    $this->withoutVite();

    config()->set('fiscal.enabled', true);
    config()->set('fiscal.base_url', 'http://127.0.0.1:8000/api');
    config()->set('fiscal.token', 'testing-fiscal-token');

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/companies/empresa-demo-prod/points-of-sale' => Http::response([
            'data' => [
                'points_of_sale' => [
                    ['number' => 2, 'type' => 'CAE', 'blocked' => false],
                    ['number' => 4, 'type' => 'MANUAL', 'blocked' => false],
                    ['number' => 5, 'type' => 'CAEA', 'blocked' => true],
                ],
            ],
        ]),
    ]);

    $superAdmin = User::factory()->superadmin()->create();
    $business = Business::factory()->create([
        'fiscal_enabled' => true,
        'fiscal_external_business_id' => 'empresa-demo-prod',
        'fiscal_environment' => 'testing',
        'fiscal_point_of_sale' => 2,
        'fiscal_document_type' => 'invoice_c',
        'fiscal_cbte_type' => 11,
    ]);

    $this
        ->actingAs($superAdmin)
        ->get(route('admin.businesses.edit', $business))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Businesses/Edit')
            ->where('fiscal_catalog.document_types.2.value', 'invoice_c')
            ->where('fiscal_catalog.document_types.2.label', 'Factura C')
            ->where('fiscal_catalog.voucher_types.6.value', 11)
            ->where('fiscal_catalog.voucher_types.6.label', 'Factura C')
            ->where('fiscal_catalog.environments.0.value', 'testing')
            ->where('sales_settings.fiscal_environment', 'testing')
            ->where('sales_settings.fiscal_point_of_sale_options.status', 'ok')
            ->where('sales_settings.fiscal_point_of_sale_options.options.0.value', 2)
            ->where('sales_settings.fiscal_point_of_sale_options.options.0.selectable', true)
            ->where('sales_settings.fiscal_point_of_sale_options.options.1.value', 4)
            ->where('sales_settings.fiscal_point_of_sale_options.options.1.selectable', false)
            ->where('sales_settings.fiscal_point_of_sale_options.options.1.disabled_reason', 'No es punto de venta electronico')
            ->where('sales_settings.fiscal_point_of_sale_options.options.2.value', 5)
            ->where('sales_settings.fiscal_point_of_sale_options.options.2.selectable', false)
            ->where('sales_settings.fiscal_point_of_sale_options.options.2.disabled_reason', 'Bloqueado por API fiscal')
            ->where('fiscal_catalog.authorization_modes.0.value', 'cae')
        );

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer testing-fiscal-token'));
});

test('superadmin cannot configure invalid fiscal cuit', function () {
    $superAdmin = User::factory()->superadmin()->create();
    $business = Business::factory()->create();

    $this->actingAs($superAdmin)
        ->from(route('admin.businesses.edit', $business))
        ->put(route('admin.businesses.sales-settings.update', $business), [
            'fiscal_enabled' => true,
            'fiscal_cuit' => '30-71234567-8',
        ])
        ->assertRedirect(route('admin.businesses.edit', $business))
        ->assertSessionHasErrors('fiscal_cuit');
});
