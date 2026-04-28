<?php

use App\Models\Business;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleFiscalDocument;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function fiscalSaleFixture(array $businessOverrides = []): array
{
    config()->set('fiscal.enabled', true);
    config()->set('fiscal.base_url', 'http://127.0.0.1:8000/api');
    config()->set('fiscal.token', 'testing-fiscal-token');
    config()->set('fiscal.defaults.point_of_sale', 2);
    config()->set('fiscal.defaults.cbte_type', 11);
    config()->set('fiscal.defaults.concept', 1);
    config()->set('fiscal.defaults.activities', []);

    $business = Business::factory()->create([
        'fiscal_enabled' => true,
        'fiscal_external_business_id' => 'empresa-demo-prod',
        'fiscal_point_of_sale' => 2,
        'fiscal_document_type' => 'invoice_c',
        'fiscal_cbte_type' => 11,
        'fiscal_concept' => 1,
        'fiscal_activities' => [492140],
        ...$businessOverrides,
    ]);

    $admin = User::factory()->businessAdmin($business->id)->create();

    $product = Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Servicio mensual',
        'slug' => 'servicio-mensual-'.$business->id,
        'unit_type' => 'unit',
        'sale_price' => 54562.74,
        'cost_price' => 0,
        'stock' => 10,
        'min_stock' => 0,
        'is_active' => true,
    ]);

    $sale = Sale::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'sale_number' => 'S-000001',
        'payment_method' => 'transfer',
        'subtotal' => 54562.74,
        'discount' => 0,
        'total' => 54562.74,
        'sold_at' => '2026-04-21 10:00:00',
    ]);

    $sale->items()->create([
        'business_id' => $business->id,
        'product_id' => $product->id,
        'product_name' => 'Servicio mensual',
        'quantity' => 1,
        'unit_price' => 54562.74,
        'subtotal' => 54562.74,
    ]);

    return [$business, $admin, $sale, $product];
}

test('sale fiscal document emission stores authorized response', function () {
    [$business, $admin, $sale] = fiscalSaleFixture();

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/documents' => Http::response([
            'id' => 'fdoc-001',
            'status' => 'authorized',
            'cae' => '12345678901234',
            'cae_expires_at' => '2026-05-01',
            'number' => 1,
            'point_of_sale' => 2,
            'cbte_type' => 11,
        ]),
    ]);

    $this
        ->actingAs($admin)
        ->from(route('sales.show', $sale))
        ->post(route('sales.fiscal-documents.store', $sale))
        ->assertRedirect(route('sales.show', $sale))
        ->assertSessionHas('success');

    $document = SaleFiscalDocument::query()->firstOrFail();

    expect($document->business_id)->toBe($business->id);
    expect($document->sale_id)->toBe($sale->id);
    expect($document->fiscal_document_id)->toBe('fdoc-001');
    expect($document->fiscal_status)->toBe(SaleFiscalDocument::STATUS_AUTHORIZED);
    expect($document->fiscal_point_of_sale)->toBe(2);
    expect($document->fiscal_cbte_type)->toBe(11);
    expect($document->fiscal_number)->toBe(1);
    expect($document->fiscal_cae)->toBe('12345678901234');
    expect($document->fiscal_cae_expires_at?->toDateString())->toBe('2026-05-01');
    expect($document->authorization_type)->toBe(SaleFiscalDocument::AUTHORIZATION_CAE);
    expect($document->authorization_code)->toBe('12345678901234');
    expect($document->authorization_expires_at?->toDateString())->toBe('2026-05-01');
    expect($document->fiscal_idempotency_key)->toBe("sale:{$business->id}:{$sale->id}:invoice");
});

test('sale fiscal document emission stores rejected response', function () {
    [, $admin, $sale] = fiscalSaleFixture();

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/documents' => Http::response([
            'id' => 'fdoc-002',
            'status' => 'rejected',
            'error' => [
                'code' => '10016',
                'message' => 'Comprobante rechazado por validacion fiscal.',
            ],
            'observations' => [
                ['code' => 'OBS', 'message' => 'Revisar datos del receptor.'],
            ],
        ]),
    ]);

    $this
        ->actingAs($admin)
        ->from(route('sales.show', $sale))
        ->post(route('sales.fiscal-documents.store', $sale))
        ->assertRedirect(route('sales.show', $sale))
        ->assertSessionHas('error');

    $document = SaleFiscalDocument::query()->firstOrFail();

    expect($document->fiscal_status)->toBe(SaleFiscalDocument::STATUS_REJECTED);
    expect($document->fiscal_error_code)->toBe('10016');
    expect($document->fiscal_error_message)->toBe('ARCA rechazo los datos del comprobante. Revisa importes, IVA, documento del receptor, tipo de comprobante y punto de venta. Detalle: Comprobante rechazado por validacion fiscal.');
    expect($document->fiscal_observations)->toBe([
        ['code' => 'OBS', 'message' => 'Revisar datos del receptor.'],
    ]);
});

test('sale fiscal document emission normalizes wrapped fiscal api resource response', function () {
    [, $admin, $sale] = fiscalSaleFixture();

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/documents' => Http::response([
            'data' => [
                'id' => 9,
                'status' => 'rejected',
                'number' => 1,
                'point_of_sale' => 2,
                'cbte_type' => 11,
                'error' => [
                    'code' => '10223',
                    'message' => 'La actividad seleccionada 492140 no se encuentra vinculada al emisor o no esta activa.',
                ],
                'observations' => [
                    'observations' => [
                        [
                            'code' => '10223',
                            'message' => 'La actividad seleccionada 492140 no se encuentra vinculada al emisor o no esta activa.',
                        ],
                    ],
                ],
            ],
            'meta' => [
                'idempotent_replay' => false,
            ],
        ], 201),
    ]);

    $this
        ->actingAs($admin)
        ->from(route('sales.show', $sale))
        ->post(route('sales.fiscal-documents.store', $sale))
        ->assertRedirect(route('sales.show', $sale))
        ->assertSessionHas('error', 'ARCA rechazo los datos del comprobante. Revisa importes, IVA, documento del receptor, tipo de comprobante y punto de venta. Detalle: La actividad seleccionada 492140 no se encuentra vinculada al emisor o no esta activa.');

    $document = SaleFiscalDocument::query()->firstOrFail();

    expect($document->fiscal_document_id)->toBe('9');
    expect($document->fiscal_status)->toBe(SaleFiscalDocument::STATUS_REJECTED);
    expect($document->fiscal_number)->toBe(1);
    expect($document->fiscal_error_code)->toBe('10223');
    expect($document->fiscal_error_message)->toBe('ARCA rechazo los datos del comprobante. Revisa importes, IVA, documento del receptor, tipo de comprobante y punto de venta. Detalle: La actividad seleccionada 492140 no se encuentra vinculada al emisor o no esta activa.');
    expect($document->fiscal_observations)->toBe([
        'observations' => [
            [
                'code' => '10223',
                'message' => 'La actividad seleccionada 492140 no se encuentra vinculada al emisor o no esta activa.',
            ],
        ],
    ]);
});

test('sale fiscal document timeout leaves local status uncertain', function () {
    [, $admin, $sale] = fiscalSaleFixture();

    Http::fake(fn () => throw new ConnectionException('timeout'));

    $this
        ->actingAs($admin)
        ->from(route('sales.show', $sale))
        ->post(route('sales.fiscal-documents.store', $sale))
        ->assertRedirect(route('sales.show', $sale))
        ->assertSessionHas('error');

    $document = SaleFiscalDocument::query()->firstOrFail();

    expect($document->fiscal_status)->toBe(SaleFiscalDocument::STATUS_UNCERTAIN);
    expect($document->fiscal_error_code)->toBe('timeout');
    expect($document->fiscal_error_message)->toBe('ARCA no respondio a tiempo. El estado del comprobante quedo incierto. Usa Conciliar antes de reintentar.');
});

test('arca 502 leaves fiscal document uncertain and blocks direct retry', function () {
    [, $admin, $sale] = fiscalSaleFixture();

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/documents' => Http::response([
            'message' => 'Bad Gateway',
        ], 502),
    ]);

    $this
        ->actingAs($admin)
        ->from(route('sales.show', $sale))
        ->post(route('sales.fiscal-documents.store', $sale))
        ->assertRedirect(route('sales.show', $sale))
        ->assertSessionHas('error', 'ARCA tuvo un error interno o de infraestructura. No se debe volver a emitir directamente. Usa Conciliar para verificar si el comprobante fue procesado.');

    $document = SaleFiscalDocument::query()->firstOrFail();

    expect($document->fiscal_status)->toBe(SaleFiscalDocument::STATUS_UNCERTAIN);
    expect($document->fiscal_error_code)->toBe('http_502');

    $this
        ->actingAs($admin)
        ->from(route('sales.show', $sale))
        ->post(route('sales.fiscal-documents.store', $sale))
        ->assertRedirect(route('sales.show', $sale))
        ->assertSessionHasErrors('fiscal');

    expect(SaleFiscalDocument::query()->where('sale_id', $sale->id)->count())->toBe(1);
    Http::assertSentCount(1);
});

test('arca 504 leaves fiscal document uncertain and asks for reconcile', function () {
    [, $admin, $sale] = fiscalSaleFixture();

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/documents' => Http::response([
            'message' => 'Gateway Timeout',
        ], 504),
    ]);

    $this
        ->actingAs($admin)
        ->from(route('sales.show', $sale))
        ->post(route('sales.fiscal-documents.store', $sale))
        ->assertRedirect(route('sales.show', $sale))
        ->assertSessionHas('error', 'ARCA no respondio a tiempo. El estado del comprobante quedo incierto. Usa Conciliar antes de reintentar.');

    $document = SaleFiscalDocument::query()->firstOrFail();

    expect($document->fiscal_status)->toBe(SaleFiscalDocument::STATUS_UNCERTAIN);
    expect($document->fiscal_error_code)->toBe('http_504');
});

test('sale fiscal document emission stores caea authorization fields', function () {
    [$business, $admin, $sale] = fiscalSaleFixture([
        'fiscal_authorization_mode' => 'caea',
    ]);

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/documents' => Http::response([
            'id' => 'fdoc-caea-001',
            'status' => 'authorized',
            'authorization_type' => 'CAEA',
            'authorization_code' => '20260412345678',
            'authorization_expires_at' => '2026-05-15',
            'number' => 12,
            'point_of_sale' => 2,
            'cbte_type' => 11,
            'caea_period' => '2026-04-2',
            'caea_order' => 2,
        ]),
    ]);

    $this
        ->actingAs($admin)
        ->post(route('sales.fiscal-documents.store', $sale))
        ->assertRedirect()
        ->assertSessionHas('success');

    $document = SaleFiscalDocument::query()->firstOrFail();

    expect($document->authorization_type)->toBe(SaleFiscalDocument::AUTHORIZATION_CAEA);
    expect($document->authorization_code)->toBe('20260412345678');
    expect($document->authorization_expires_at?->toDateString())->toBe('2026-05-15');
    expect($document->caea_period)->toBe('2026-04-2');
    expect($document->caea_order)->toBe(2);
    expect($document->caea_report_status)->toBe(SaleFiscalDocument::CAEA_REPORT_PENDING);
    expect($document->fiscal_cae)->toBeNull();

    Http::assertSent(function (Request $request) use ($sale): bool {
        return $request->data()['business_id'] === 'empresa-demo-prod'
            && $request->data()['origin_id'] === (string) $sale->id
            && $request->data()['authorization_mode'] === 'caea';
    });
});

test('authorized fiscal document is not emitted again', function () {
    [$business, $admin, $sale] = fiscalSaleFixture();

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

    Http::fake();

    $this
        ->actingAs($admin)
        ->from(route('sales.show', $sale))
        ->post(route('sales.fiscal-documents.store', $sale))
        ->assertRedirect(route('sales.show', $sale))
        ->assertSessionHasErrors('fiscal');

    Http::assertNothingSent();
    expect(SaleFiscalDocument::query()->count())->toBe(1);
});

test('sale fiscal emission is forbidden when electronic billing module is disabled', function () {
    [, $admin, $sale] = fiscalSaleFixture([
        'fiscal_enabled' => false,
    ]);

    Http::fake();

    $this
        ->actingAs($admin)
        ->post(route('sales.fiscal-documents.store', $sale))
        ->assertForbidden();

    Http::assertNothingSent();
    expect(SaleFiscalDocument::query()->count())->toBe(0);
});

test('rejected fiscal document retry uses a new idempotency key', function () {
    [$business, $admin, $sale] = fiscalSaleFixture();

    SaleFiscalDocument::query()->create([
        'business_id' => $business->id,
        'sale_id' => $sale->id,
        'attempt_number' => 1,
        'fiscal_document_id' => 'fdoc-rejected',
        'fiscal_status' => SaleFiscalDocument::STATUS_REJECTED,
        'fiscal_point_of_sale' => 2,
        'fiscal_cbte_type' => 11,
        'fiscal_error_code' => '10016',
        'fiscal_error_message' => 'Rechazo inicial.',
        'fiscal_idempotency_key' => "sale:{$business->id}:{$sale->id}:invoice",
        'attempted_at' => now(),
    ]);

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/documents' => Http::response([
            'id' => 'fdoc-retry',
            'status' => 'authorized',
            'cae' => '32345678901234',
            'cae_expires_at' => '2026-05-01',
            'number' => 2,
            'point_of_sale' => 2,
            'cbte_type' => 11,
        ]),
    ]);

    $this
        ->actingAs($admin)
        ->post(route('sales.fiscal-documents.store', $sale))
        ->assertRedirect()
        ->assertSessionHas('success');

    $documents = SaleFiscalDocument::query()
        ->where('sale_id', $sale->id)
        ->orderBy('attempt_number')
        ->get();

    expect($documents)->toHaveCount(2);
    expect($documents[1]->attempt_number)->toBe(2);
    expect($documents[1]->fiscal_status)->toBe(SaleFiscalDocument::STATUS_AUTHORIZED);
    expect($documents[1]->fiscal_idempotency_key)->toBe("sale:{$business->id}:{$sale->id}:invoice:retry:2");

    Http::assertSent(function (Request $request) use ($business, $sale): bool {
        return $request->data()['idempotency_key'] === "sale:{$business->id}:{$sale->id}:invoice:retry:2";
    });
});

test('sale fiscal payload is generated from sale data', function () {
    [$business, $admin, $sale] = fiscalSaleFixture([
        'fiscal_concept' => 2,
    ]);

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/documents' => Http::response([
            'id' => 'fdoc-003',
            'status' => 'authorized',
            'cae' => '22345678901234',
            'cae_expires_at' => '2026-05-01',
            'number' => 7,
            'point_of_sale' => 2,
            'cbte_type' => 11,
        ]),
    ]);

    $this
        ->actingAs($admin)
        ->post(route('sales.fiscal-documents.store', $sale))
        ->assertRedirect();

    Http::assertSent(function (Request $request) use ($business, $sale): bool {
        $payload = $request->data();

        return $request->url() === 'http://127.0.0.1:8000/api/fiscal/documents'
            && $request->hasHeader('Authorization', 'Bearer testing-fiscal-token')
            && $payload['business_id'] === 'empresa-demo-prod'
            && $payload['sale_id'] === 'S-000001'
            && $payload['origin_type'] === 'sale'
            && $payload['origin_id'] === (string) $sale->id
            && $payload['document_type'] === 'invoice_c'
            && $payload['voucher_date'] === '2026-04-21'
            && $payload['point_of_sale'] === 2
            && $payload['cbte_type'] === 11
            && $payload['concept'] === 2
            && $payload['customer']['doc_type'] === 99
            && $payload['customer']['doc_number'] === 0
            && $payload['customer']['tax_condition_id'] === 5
            && $payload['amounts']['imp_total'] === 54562.74
            && $payload['amounts']['imp_neto'] === 54562.74
            && $payload['amounts']['imp_iva'] === 0.0
            && $payload['currency'] === 'PES'
            && $payload['currency_rate'] === 1.0
            && $payload['service_dates']['from'] === '2026-04-21'
            && $payload['activities'] === [492140]
            && $payload['items'][0]['description'] === 'Servicio mensual'
            && $payload['items'][0]['quantity'] === 1.0
            && $payload['items'][0]['unit'] === 'unidades'
            && $payload['items'][0]['unit_price'] === 54562.74
            && $payload['items'][0]['subtotal'] === 54562.74
            && $payload['idempotency_key'] === "sale:{$business->id}:{$sale->id}:invoice";
    });
});
