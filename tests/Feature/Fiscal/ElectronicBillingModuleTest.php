<?php

use App\Models\Business;
use App\Models\Sale;
use App\Models\SaleFiscalDocument;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function electronicBillingBusinessFixture(bool $enabled = true): array
{
    config()->set('fiscal.enabled', true);
    config()->set('fiscal.base_url', 'http://127.0.0.1:8000/api');
    config()->set('fiscal.token', 'testing-fiscal-token');

    $business = Business::factory()->create([
        'fiscal_enabled' => $enabled,
        'fiscal_external_business_id' => 'empresa-demo-prod',
        'fiscal_cuit' => '30712345671',
        'fiscal_point_of_sale' => 2,
        'fiscal_document_type' => 'invoice_c',
        'fiscal_cbte_type' => 11,
        'fiscal_concept' => 1,
        'fiscal_activities' => [492140],
    ]);

    $admin = User::factory()->businessAdmin($business->id)->create();

    return [$business, $admin];
}

test('electronic billing module is hidden and blocked when disabled for business', function () {
    $this->withoutVite();

    [, $admin] = electronicBillingBusinessFixture(false);

    $this
        ->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('modules.electronic_billing.enabled', false)
        );

    $this
        ->actingAs($admin)
        ->get(route('electronic-billing.index'))
        ->assertForbidden();
});

test('electronic billing module shows fiscal api status and recent documents when enabled', function () {
    $this->withoutVite();

    [$business, $admin] = electronicBillingBusinessFixture();

    $sale = Sale::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'sale_number' => 'S-000001',
        'payment_method' => 'transfer',
        'subtotal' => 1500,
        'discount' => 0,
        'total' => 1500,
        'sold_at' => '2026-04-21 10:00:00',
    ]);

    SaleFiscalDocument::query()->create([
        'business_id' => $business->id,
        'sale_id' => $sale->id,
        'attempt_number' => 1,
        'fiscal_document_id' => 'fdoc-001',
        'fiscal_status' => SaleFiscalDocument::STATUS_AUTHORIZED,
        'fiscal_point_of_sale' => 2,
        'fiscal_cbte_type' => 11,
        'fiscal_number' => 1,
        'fiscal_cae' => '12345678901234',
        'fiscal_cae_expires_at' => '2026-05-01',
        'fiscal_idempotency_key' => "sale:{$business->id}:{$sale->id}:invoice",
        'attempted_at' => now(),
        'authorized_at' => now(),
    ]);

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/companies/empresa-demo-prod/status' => Http::response([
            'ready' => true,
            'status_label' => 'Listo',
            'environment' => 'testing',
            'message' => 'Empresa fiscal configurada.',
            'credential' => [
                'configured' => true,
                'id' => 17,
                'key_name' => 'empresa-demo.key',
                'status' => 'active',
                'active' => true,
                'csr_generated' => true,
                'certificate_loaded' => true,
                'certificate_expires_at' => '2027-04-30T00:00:00-03:00',
            ],
        ]),
        'http://127.0.0.1:8000/api/fiscal/companies/empresa-demo-prod/activities' => Http::response([
            'activities' => [['code' => 492140, 'name' => 'Servicios']],
        ]),
        'http://127.0.0.1:8000/api/fiscal/companies/empresa-demo-prod/points-of-sale' => Http::response([
            'points_of_sale' => [['number' => 2, 'type' => 'CAE']],
        ]),
    ]);

    $this
        ->actingAs($admin)
        ->get(route('electronic-billing.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Fiscal/Index')
            ->where('configuration.external_business_id', 'empresa-demo-prod')
            ->where('configuration.fiscal_cuit', '30712345671')
            ->where('configuration.point_of_sale', 2)
            ->where('connection.status', 'connected')
            ->where('setup.ready', true)
            ->where('setup.credential.id', 17)
            ->where('setup.credential.csr_generated', true)
            ->where('setup.credential.certificate_loaded', true)
            ->where('diagnostics.requested', false)
            ->where('iva_sales_book.requested', false)
            ->where('can_manage_credentials', true)
            ->where('summary.authorized', 1)
            ->where('documents.0.status', SaleFiscalDocument::STATUS_AUTHORIZED)
            ->where('documents.0.sale_number', 'S-000001')
        );

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer testing-fiscal-token'));
});

test('electronic billing module runs fiscal api diagnostics only when requested', function () {
    $this->withoutVite();

    [, $admin] = electronicBillingBusinessFixture();

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/companies/empresa-demo-prod/status' => Http::response([
            'data' => [
                'ready' => true,
                'status_label' => 'Listo',
                'environment' => 'testing',
                'message' => 'Empresa fiscal operativa.',
                'credential' => [
                    'configured' => true,
                    'active' => true,
                    'csr_generated' => true,
                    'certificate_loaded' => true,
                ],
            ],
        ]),
        'http://127.0.0.1:8000/api/fiscal/companies/empresa-demo-prod/activities' => Http::response([
            'data' => ['activities' => []],
        ]),
        'http://127.0.0.1:8000/api/fiscal/companies/empresa-demo-prod/points-of-sale' => Http::response([
            'data' => ['points_of_sale' => []],
        ]),
        'http://127.0.0.1:8000/api/fiscal/companies/empresa-demo-prod/diagnostics' => Http::response([
            'data' => [
                'ok' => false,
                'environment' => 'testing',
                'checks' => [
                    'company' => [
                        'ok' => true,
                        'message' => 'Empresa fiscal habilitada.',
                    ],
                    'credential' => [
                        'ok' => false,
                        'message' => 'Falta credencial activa.',
                        'error_code' => 'credentials_missing',
                    ],
                ],
            ],
        ]),
    ]);

    $this
        ->actingAs($admin)
        ->get(route('electronic-billing.index', ['run_diagnostics' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Fiscal/Index')
            ->where('diagnostics.requested', true)
            ->where('diagnostics.ok', false)
            ->where('diagnostics.status', 'warning')
            ->where('diagnostics.checks.0.key', 'company')
            ->where('diagnostics.checks.0.ok', true)
            ->where('diagnostics.checks.1.key', 'credential')
            ->where('diagnostics.checks.1.error_code', 'credentials_missing')
        );

    Http::assertSentCount(4);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://127.0.0.1:8000/api/fiscal/companies/empresa-demo-prod/diagnostics'
        && $request->method() === 'GET');
});

test('electronic billing module loads iva sales book when requested', function () {
    $this->withoutVite();

    [, $admin] = electronicBillingBusinessFixture();

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/companies/empresa-demo-prod/status' => Http::response([
            'data' => [
                'ready' => true,
                'status_label' => 'Listo',
                'environment' => 'testing',
                'message' => 'Empresa fiscal operativa.',
                'credential' => [
                    'configured' => true,
                    'active' => true,
                    'csr_generated' => true,
                    'certificate_loaded' => true,
                ],
            ],
        ]),
        'http://127.0.0.1:8000/api/fiscal/companies/empresa-demo-prod/activities' => Http::response([
            'data' => ['activities' => []],
        ]),
        'http://127.0.0.1:8000/api/fiscal/companies/empresa-demo-prod/points-of-sale' => Http::response([
            'data' => ['points_of_sale' => []],
        ]),
        'http://127.0.0.1:8000/api/fiscal/documents/iva-sales*' => Http::response([
            'data' => [
                'period' => [
                    'date_from' => '2026-04-01',
                    'date_to' => '2026-04-30',
                ],
                'records' => [
                    [
                        'id' => 10,
                        'voucher_date' => '2026-04-21',
                        'cbte_type' => 1,
                        'point_of_sale' => 2,
                        'number' => 7,
                        'counterparty_name' => 'Cliente SA',
                        'counterparty_cuit' => '30712345671',
                        'amounts' => [
                            'imp_total' => '121.00',
                            'imp_neto' => '100.00',
                            'imp_iva' => '21.00',
                        ],
                        'iva_items' => [
                            ['id' => 5, 'rate' => '21.00', 'base_imp' => '100.00', 'importe' => '21.00'],
                        ],
                    ],
                ],
                'totals' => [
                    'imp_total' => '121.00',
                    'imp_neto' => '100.00',
                    'imp_iva' => '21.00',
                    'iva_by_aliquot' => [
                        ['id' => 5, 'rate' => '21.00', 'base_imp' => '100.00', 'importe' => '21.00'],
                    ],
                ],
            ],
        ]),
    ]);

    $this
        ->actingAs($admin)
        ->get(route('electronic-billing.index', [
            'load_iva_sales' => 1,
            'iva_month' => '2026-04',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Fiscal/Index')
            ->where('iva_sales_book.requested', true)
            ->where('iva_sales_book.ok', true)
            ->where('iva_sales_book.period.month', '2026-04')
            ->where('iva_sales_book.period.date_from', '2026-04-01')
            ->where('iva_sales_book.period.date_to', '2026-04-30')
            ->where('iva_sales_book.records.0.counterparty_name', 'Cliente SA')
            ->where('iva_sales_book.records.0.amounts.imp_iva', 21)
            ->where('iva_sales_book.totals.imp_total', 121)
            ->where('iva_sales_book.totals.iva_by_aliquot.0.id', 5)
        );

    Http::assertSentCount(4);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'http://127.0.0.1:8000/api/fiscal/documents/iva-sales')
        && str_contains($request->url(), 'business_id=empresa-demo-prod')
        && str_contains($request->url(), 'date_from=2026-04-01')
        && str_contains($request->url(), 'date_to=2026-04-30')
        && $request->method() === 'GET');
});

test('business admin generates fiscal credential csr through fiscal api proxy', function () {
    $this->withoutVite();

    [, $admin] = electronicBillingBusinessFixture();

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/companies/empresa-demo-prod/credentials/csr' => Http::response([
            'data' => [
                'credential' => [
                    'id' => 17,
                    'key_name' => 'empresa-demo.key',
                    'status' => 'pending_certificate',
                ],
                'csr' => "-----BEGIN CERTIFICATE REQUEST-----\ntest\n-----END CERTIFICATE REQUEST-----",
            ],
            'meta' => [
                'created' => true,
            ],
        ], 201),
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
        ->assertSessionHas('success', 'CSR generado por la API fiscal.')
        ->assertSessionHas('fiscal_credential_onboarding', fn (array $payload): bool => $payload['credential_id'] === 17
            && $payload['key_name'] === 'empresa-demo.key'
            && str_contains($payload['csr'], 'BEGIN CERTIFICATE REQUEST'));

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'http://127.0.0.1:8000/api/fiscal/companies/empresa-demo-prod/credentials/csr'
        && $request->hasHeader('Authorization', 'Bearer testing-fiscal-token')
        && $request->data()['key_name'] === 'empresa-demo.key'
        && ! array_key_exists('private_key', $request->data()));
});

test('business admin csr key name validation matches fiscal api contract', function () {
    $this->withoutVite();

    [, $admin] = electronicBillingBusinessFixture();

    Http::fake();

    $this
        ->actingAs($admin)
        ->post(route('electronic-billing.credentials.csr'), [
            'key_name' => '.empresa-demo.key',
            'common_name' => str_repeat('a', 65),
            'organization_name' => 'Empresa Demo SA',
            'country_name' => 'AR',
        ])
        ->assertSessionHasErrors(['key_name', 'common_name']);

    Http::assertNothingSent();
});

test('business admin uploads fiscal certificate through fiscal api proxy without local storage', function () {
    $this->withoutVite();

    [, $admin] = electronicBillingBusinessFixture();
    $certificate = "-----BEGIN CERTIFICATE-----\ntest\n-----END CERTIFICATE-----";

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/companies/empresa-demo-prod/credentials/17/certificate' => Http::response([
            'data' => [
                'credential' => [
                    'id' => 17,
                    'status' => 'active',
                    'active' => true,
                ],
            ],
        ]),
    ]);

    $this
        ->actingAs($admin)
        ->post(route('electronic-billing.credentials.certificate.store'), [
            'credential_id' => 17,
            'certificate' => $certificate,
            'active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Certificado cargado en la API fiscal.');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request->url() === 'http://127.0.0.1:8000/api/fiscal/companies/empresa-demo-prod/credentials/17/certificate'
        && $request->hasHeader('Authorization', 'Bearer testing-fiscal-token')
        && $request->data()['certificate'] === $certificate
        && $request->data()['active'] === true
        && ! array_key_exists('private_key', $request->data()));
});

test('business staff cannot proxy fiscal credential onboarding', function () {
    $this->withoutVite();

    [$business] = electronicBillingBusinessFixture();
    $staff = User::factory()->staff($business->id)->create();

    $this
        ->actingAs($staff)
        ->post(route('electronic-billing.credentials.csr'), [
            'key_name' => 'empresa-demo.key',
        ])
        ->assertForbidden();

    Http::assertNothingSent();
});

test('electronic billing module normalizes nested fiscal api setup payload', function () {
    $this->withoutVite();

    [, $admin] = electronicBillingBusinessFixture();

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/companies/empresa-demo-prod/status' => Http::response([
            'data' => [
                'business_id' => 'empresa-demo-prod',
                'ready' => true,
                'status_label' => 'Listo',
                'environment' => 'testing',
                'message' => 'Empresa fiscal operativa.',
                'credential' => [
                    'configured' => true,
                    'id' => 23,
                    'key_name' => 'empresa-demo-prod.key',
                    'status' => 'pending_certificate',
                    'active' => false,
                    'csr_generated' => true,
                    'certificate_loaded' => false,
                    'certificate_expires_at' => null,
                ],
            ],
        ]),
        'http://127.0.0.1:8000/api/fiscal/companies/empresa-demo-prod/activities' => Http::response([
            'data' => [
                'company_id' => 1,
                'business_id' => 'empresa-demo-prod',
                'environment' => 'testing',
                'activities' => [['code' => 492140, 'name' => 'Servicios']],
            ],
        ]),
        'http://127.0.0.1:8000/api/fiscal/companies/empresa-demo-prod/points-of-sale' => Http::response([
            'data' => [
                'company_id' => 1,
                'business_id' => 'empresa-demo-prod',
                'environment' => 'testing',
                'points_of_sale' => [['number' => 2, 'type' => 'CAE']],
            ],
        ]),
    ]);

    $this
        ->actingAs($admin)
        ->get(route('electronic-billing.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Fiscal/Index')
            ->where('setup.ready', true)
            ->where('setup.status_label', 'Listo')
            ->where('setup.environment', 'testing')
            ->where('setup.credential.id', 23)
            ->where('setup.credential.csr_generated', true)
            ->where('setup.credential.certificate_loaded', false)
            ->where('activities.0.code', 492140)
            ->where('activities.0.name', 'Servicios')
            ->where('points_of_sale.0.number', 2)
        );
});

test('electronic billing module shows unavailable state when fiscal api is offline', function () {
    $this->withoutVite();

    [, $admin] = electronicBillingBusinessFixture();

    Http::fake(function () {
        throw new ConnectionException('Connection refused');
    });

    $this
        ->actingAs($admin)
        ->get(route('electronic-billing.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Fiscal/Index')
            ->where('connection.status', 'offline')
            ->where('connection.status_label', 'No disponible')
            ->where('connection.ok', false)
            ->where('connection.message', 'La API fiscal no respondio a tiempo. El estado del comprobante quedo incierto. Usa Conciliar antes de reintentar.')
            ->where('setup.ready', false)
            ->where('setup.status_label', 'No verificado')
            ->where('activities', [])
            ->where('points_of_sale', [])
        );
});
