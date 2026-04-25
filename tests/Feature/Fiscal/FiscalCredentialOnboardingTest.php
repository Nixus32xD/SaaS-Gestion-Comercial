<?php

use App\Models\Business;
use App\Models\BusinessFiscalCredential;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function fiscalCredentialOnboardingFixture(): array
{
    config()->set('fiscal.enabled', true);
    config()->set('fiscal.base_url', 'http://127.0.0.1:8000/api');
    config()->set('fiscal.token', 'testing-fiscal-token');

    $business = Business::factory()->create([
        'name' => 'Empresa Demo SA',
        'fiscal_enabled' => true,
        'fiscal_external_business_id' => 'empresa-demo-prod',
        'fiscal_cuit' => '30712345671',
        'fiscal_point_of_sale' => 2,
        'fiscal_document_type' => 'invoice_c',
        'fiscal_cbte_type' => 11,
        'fiscal_concept' => 1,
    ]);

    $admin = User::factory()->businessAdmin($business->id)->create();

    return [$business, $admin];
}

test('business admin can generate fiscal csr without sending private key data', function () {
    [$business, $admin] = fiscalCredentialOnboardingFixture();

    $csr = "-----BEGIN CERTIFICATE REQUEST-----\nCSR-DEMO\n-----END CERTIFICATE REQUEST-----";

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/companies/empresa-demo-prod/credentials/csr' => Http::response([
            'data' => [
                'company_id' => 1,
                'business_id' => 'empresa-demo-prod',
                'credential' => [
                    'id' => 10,
                    'key_name' => 'empresa-demo.key',
                    'status' => 'pending_certificate',
                    'active' => false,
                    'certificate_expires_at' => null,
                    'metadata' => [],
                ],
                'csr' => $csr,
            ],
            'meta' => [
                'created' => true,
            ],
        ], 201),
    ]);

    $this
        ->actingAs($admin)
        ->from(route('electronic-billing.index'))
        ->post(route('electronic-billing.credentials.csr'), [
            'key_name' => 'empresa-demo.key',
            'common_name' => 'empresa-demo-prod',
            'organization_name' => 'Empresa Demo SA',
            'country_name' => 'AR',
        ])
        ->assertRedirect(route('electronic-billing.index'))
        ->assertSessionHas('success');

    $credential = BusinessFiscalCredential::query()->firstOrFail();

    expect($credential->business_id)->toBe($business->id);
    expect($credential->fiscal_business_id)->toBe('empresa-demo-prod');
    expect($credential->fiscal_credential_id)->toBe('10');
    expect($credential->key_name)->toBe('empresa-demo.key');
    expect($credential->status)->toBe(BusinessFiscalCredential::STATUS_PENDING_CERTIFICATE);
    expect($credential->csr)->toBe($csr);

    Http::assertSent(function (Request $request) use ($admin): bool {
        $payload = $request->data();

        return $request->hasHeader('Authorization', 'Bearer testing-fiscal-token')
            && $payload['key_name'] === 'empresa-demo.key'
            && $payload['metadata']['source'] === 'saas'
            && $payload['metadata']['requested_by_user_id'] === (string) $admin->id
            && $payload['metadata']['fiscal_cuit'] === '30712345671'
            && ! array_key_exists('private_key', $payload)
            && ! array_key_exists('certificate', $payload);
    });
});

test('business admin can reuse an existing fiscal csr for the same key name', function () {
    [$business, $admin] = fiscalCredentialOnboardingFixture();

    BusinessFiscalCredential::query()->create([
        'business_id' => $business->id,
        'fiscal_business_id' => 'empresa-demo-prod',
        'fiscal_credential_id' => '10',
        'key_name' => 'empresa-demo.key',
        'status' => BusinessFiscalCredential::STATUS_PENDING_CERTIFICATE,
        'csr' => "-----BEGIN CERTIFICATE REQUEST-----\nOLD\n-----END CERTIFICATE REQUEST-----",
    ]);

    $csr = "-----BEGIN CERTIFICATE REQUEST-----\nREUSED\n-----END CERTIFICATE REQUEST-----";

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/companies/empresa-demo-prod/credentials/csr' => Http::response([
            'data' => [
                'business_id' => 'empresa-demo-prod',
                'credential' => [
                    'id' => 10,
                    'key_name' => 'empresa-demo.key',
                    'status' => 'pending_certificate',
                    'active' => false,
                ],
                'csr' => $csr,
            ],
            'meta' => [
                'created' => false,
            ],
        ]),
    ]);

    $this
        ->actingAs($admin)
        ->post(route('electronic-billing.credentials.csr'), [
            'key_name' => 'empresa-demo.key',
            'common_name' => 'empresa-demo-prod',
            'organization_name' => 'Empresa Demo SA',
            'country_name' => 'AR',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(BusinessFiscalCredential::query()->count())->toBe(1);
    expect(BusinessFiscalCredential::query()->firstOrFail()->csr)->toBe($csr);
});

test('business admin can upload matching certificate and activate local credential', function () {
    [$business, $admin] = fiscalCredentialOnboardingFixture();

    $credential = BusinessFiscalCredential::query()->create([
        'business_id' => $business->id,
        'fiscal_business_id' => 'empresa-demo-prod',
        'fiscal_credential_id' => '10',
        'key_name' => 'empresa-demo.key',
        'status' => BusinessFiscalCredential::STATUS_PENDING_CERTIFICATE,
        'csr' => "-----BEGIN CERTIFICATE REQUEST-----\nCSR\n-----END CERTIFICATE REQUEST-----",
    ]);

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/companies/empresa-demo-prod/credentials/10/certificate' => Http::response([
            'data' => [
                'business_id' => 'empresa-demo-prod',
                'credential' => [
                    'id' => 10,
                    'key_name' => 'empresa-demo.key',
                    'status' => 'active',
                    'active' => true,
                    'certificate_expires_at' => '2027-04-24T00:00:00-03:00',
                    'metadata' => [],
                ],
            ],
        ]),
    ]);

    $certificate = "-----BEGIN CERTIFICATE-----\nCRT-DEMO\n-----END CERTIFICATE-----";

    $this
        ->actingAs($admin)
        ->post(route('electronic-billing.credentials.certificate.store', $credential), [
            'certificate' => $certificate,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $credential->refresh();

    expect($credential->status)->toBe(BusinessFiscalCredential::STATUS_ACTIVE);
    expect($credential->certificate_expires_at?->toDateString())->toBe('2027-04-24');
    expect($credential->last_error_code)->toBeNull();

    Http::assertSent(function (Request $request) use ($admin, $certificate): bool {
        $payload = $request->data();

        return $payload['certificate'] === $certificate
            && $payload['active'] === true
            && $payload['metadata']['source'] === 'saas'
            && $payload['metadata']['uploaded_by_user_id'] === (string) $admin->id
            && ! array_key_exists('private_key', $payload);
    });
});

test('certificate upload uses the fiscal company persisted with the csr', function () {
    [$business, $admin] = fiscalCredentialOnboardingFixture();
    $business->update(['fiscal_external_business_id' => 'empresa-renombrada']);

    $credential = BusinessFiscalCredential::query()->create([
        'business_id' => $business->id,
        'fiscal_business_id' => 'empresa-demo-prod',
        'fiscal_credential_id' => '10',
        'key_name' => 'empresa-demo.key',
        'status' => BusinessFiscalCredential::STATUS_PENDING_CERTIFICATE,
        'csr' => "-----BEGIN CERTIFICATE REQUEST-----\nCSR\n-----END CERTIFICATE REQUEST-----",
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/companies/empresa-demo-prod/credentials/10/certificate' => Http::response([
            'data' => [
                'business_id' => 'empresa-demo-prod',
                'credential' => [
                    'id' => 10,
                    'key_name' => 'empresa-demo.key',
                    'status' => 'active',
                    'active' => true,
                ],
            ],
        ]),
    ]);

    $this
        ->actingAs($admin)
        ->post(route('electronic-billing.credentials.certificate.store', $credential), [
            'certificate' => "-----BEGIN CERTIFICATE-----\nCRT-DEMO\n-----END CERTIFICATE-----",
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/fiscal/companies/empresa-demo-prod/'));
});

test('company not found is shown as a clear certificate error', function () {
    [$business, $admin] = fiscalCredentialOnboardingFixture();

    $credential = BusinessFiscalCredential::query()->create([
        'business_id' => $business->id,
        'fiscal_business_id' => 'empresa-inexistente',
        'fiscal_credential_id' => '10',
        'key_name' => 'empresa-demo.key',
        'status' => BusinessFiscalCredential::STATUS_PENDING_CERTIFICATE,
        'csr' => "-----BEGIN CERTIFICATE REQUEST-----\nCSR\n-----END CERTIFICATE REQUEST-----",
    ]);

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/companies/empresa-inexistente/credentials/10/certificate' => Http::response([
            'message' => 'Fiscal company was not found.',
            'error_code' => 'company_not_found',
        ], 404),
    ]);

    $this
        ->actingAs($admin)
        ->from(route('electronic-billing.index'))
        ->post(route('electronic-billing.credentials.certificate.store', $credential), [
            'certificate' => "-----BEGIN CERTIFICATE-----\nCRT-DEMO\n-----END CERTIFICATE-----",
        ])
        ->assertRedirect(route('electronic-billing.index'))
        ->assertSessionHasErrors([
            'certificate' => 'La API fiscal no encontro la empresa fiscal configurada. Revisa que el ID externo del comercio exista en la API fiscal y coincida con el CSR generado.',
        ]);

    $credential->refresh();

    expect($credential->status)->toBe(BusinessFiscalCredential::STATUS_ERROR);
    expect($credential->last_error_code)->toBe('company_not_found');
    expect($credential->last_error_message)->toBe('La API fiscal no encontro la empresa fiscal configurada. Revisa que el ID externo del comercio exista en la API fiscal y coincida con el CSR generado.');
});

test('certificate private key mismatch is shown as a clear validation error', function () {
    [$business, $admin] = fiscalCredentialOnboardingFixture();

    $credential = BusinessFiscalCredential::query()->create([
        'business_id' => $business->id,
        'fiscal_business_id' => 'empresa-demo-prod',
        'fiscal_credential_id' => '10',
        'key_name' => 'empresa-demo.key',
        'status' => BusinessFiscalCredential::STATUS_PENDING_CERTIFICATE,
        'csr' => "-----BEGIN CERTIFICATE REQUEST-----\nCSR\n-----END CERTIFICATE REQUEST-----",
    ]);

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/companies/empresa-demo-prod/credentials/10/certificate' => Http::response([
            'message' => 'Mismatch',
            'error_code' => 'certificate_private_key_mismatch',
        ], 409),
    ]);

    $this
        ->actingAs($admin)
        ->from(route('electronic-billing.index'))
        ->post(route('electronic-billing.credentials.certificate.store', $credential), [
            'certificate' => "-----BEGIN CERTIFICATE-----\nBAD\n-----END CERTIFICATE-----",
        ])
        ->assertRedirect(route('electronic-billing.index'))
        ->assertSessionHasErrors('certificate');

    $credential->refresh();

    expect($credential->status)->toBe(BusinessFiscalCredential::STATUS_ERROR);
    expect($credential->last_error_code)->toBe('certificate_private_key_mismatch');
});

test('business admin can test active fiscal credentials', function () {
    [$business, $admin] = fiscalCredentialOnboardingFixture();

    $credential = BusinessFiscalCredential::query()->create([
        'business_id' => $business->id,
        'fiscal_business_id' => 'empresa-demo-prod',
        'fiscal_credential_id' => '10',
        'key_name' => 'empresa-demo.key',
        'status' => BusinessFiscalCredential::STATUS_ACTIVE,
    ]);

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/companies/empresa-demo-prod/credentials/test' => Http::response([
            'data' => [
                'ok' => true,
                'environment' => 'testing',
            ],
        ]),
    ]);

    $this
        ->actingAs($admin)
        ->post(route('electronic-billing.credentials.test', $credential))
        ->assertRedirect()
        ->assertSessionHas('success');

    $credential->refresh();

    expect($credential->last_test_status)->toBe('ok');
    expect($credential->last_tested_at)->not()->toBeNull();
    expect($credential->status)->toBe(BusinessFiscalCredential::STATUS_ACTIVE);
});

test('arca http error while testing credentials is shown clearly', function () {
    [$business, $admin] = fiscalCredentialOnboardingFixture();

    $credential = BusinessFiscalCredential::query()->create([
        'business_id' => $business->id,
        'fiscal_business_id' => 'empresa-demo-prod',
        'fiscal_credential_id' => '10',
        'key_name' => 'empresa-demo.key',
        'status' => BusinessFiscalCredential::STATUS_ACTIVE,
    ]);

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/companies/empresa-demo-prod/credentials/test' => Http::response([
            'message' => 'ARCA returned HTTP status [500].',
            'error_code' => 'arca_http_error',
        ], 502),
    ]);

    $expectedMessage = 'ARCA respondio con un error interno al probar las credenciales. Intenta nuevamente en unos minutos; si persiste, revisa en la API fiscal los logs de WSAA/WSFE y que el certificado este delegado al web service correcto.';

    $this
        ->actingAs($admin)
        ->from(route('electronic-billing.index'))
        ->post(route('electronic-billing.credentials.test', $credential))
        ->assertRedirect(route('electronic-billing.index'))
        ->assertSessionHasErrors([
            'test' => $expectedMessage,
        ]);

    $credential->refresh();

    expect($credential->status)->toBe(BusinessFiscalCredential::STATUS_ACTIVE);
    expect($credential->last_test_status)->toBe('error');
    expect($credential->last_error_code)->toBe('arca_http_error');
    expect($credential->last_error_message)->toBe($expectedMessage);
});

test('electronic billing page does not reuse credentials from another fiscal company', function () {
    [$business, $admin] = fiscalCredentialOnboardingFixture();
    $business->update(['fiscal_external_business_id' => 'uber-pagos']);

    BusinessFiscalCredential::query()->create([
        'business_id' => $business->id,
        'fiscal_business_id' => 'empresa-demo',
        'fiscal_credential_id' => '3',
        'key_name' => 'uber-pagos.key',
        'status' => BusinessFiscalCredential::STATUS_ACTIVE,
    ]);

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/companies/uber-pagos/status' => Http::response([
            'message' => 'Fiscal company was not found.',
            'error_code' => 'company_not_found',
        ], 404),
        'http://127.0.0.1:8000/api/fiscal/companies/uber-pagos/activities' => Http::response([
            'message' => 'Fiscal company was not found.',
            'error_code' => 'company_not_found',
        ], 404),
        'http://127.0.0.1:8000/api/fiscal/companies/uber-pagos/points-of-sale' => Http::response([
            'message' => 'Fiscal company was not found.',
            'error_code' => 'company_not_found',
        ], 404),
    ]);

    $this
        ->actingAs($admin)
        ->get(route('electronic-billing.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Fiscal/Index')
            ->where('connection.status', 'error')
            ->where('connection.status_label', 'No encontrada')
            ->where('connection.ok', false)
            ->where('connection.message', "La API fiscal no encontro la empresa fiscal 'uber-pagos'. Crea esa company en la API fiscal o corrige el ID externo del comercio.")
            ->where('onboarding.status', 'sin_configurar')
            ->where('onboarding.credential', null)
        );
});

test('electronic billing page exposes fiscal onboarding state', function () {
    [$business, $admin] = fiscalCredentialOnboardingFixture();

    BusinessFiscalCredential::query()->create([
        'business_id' => $business->id,
        'fiscal_business_id' => 'empresa-demo-prod',
        'fiscal_credential_id' => '10',
        'key_name' => 'empresa-demo.key',
        'status' => BusinessFiscalCredential::STATUS_PENDING_CERTIFICATE,
        'csr' => "-----BEGIN CERTIFICATE REQUEST-----\nCSR\n-----END CERTIFICATE REQUEST-----",
    ]);

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/companies/empresa-demo-prod/status' => Http::response([
            'data' => [
                'enabled' => true,
                'credential' => ['configured' => false, 'active' => false],
                'access_ticket' => ['configured' => false, 'valid' => false],
            ],
        ]),
        'http://127.0.0.1:8000/api/fiscal/companies/empresa-demo-prod/activities' => Http::response([
            'data' => ['activities' => []],
        ]),
        'http://127.0.0.1:8000/api/fiscal/companies/empresa-demo-prod/points-of-sale' => Http::response([
            'data' => ['points_of_sale' => []],
        ]),
    ]);

    $this
        ->actingAs($admin)
        ->get(route('electronic-billing.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Fiscal/Index')
            ->where('onboarding.can_manage', true)
            ->where('onboarding.status', BusinessFiscalCredential::STATUS_PENDING_CERTIFICATE)
            ->where('onboarding.fiscal_cuit', '30712345671')
            ->where('configuration.fiscal_cuit', '30712345671')
            ->where('onboarding.credential.key_name', 'empresa-demo.key')
            ->where('onboarding.credential.fiscal_credential_id', '10')
        );
});
