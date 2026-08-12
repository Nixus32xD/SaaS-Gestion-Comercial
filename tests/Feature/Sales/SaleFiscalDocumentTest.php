<?php

use App\Models\Business;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleFiscalDocument;
use App\Models\User;
use App\Services\Fiscal\FiscalQrService;
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

function fiscalSaleStoreFixture(array $businessOverrides = []): array
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
        'name' => 'Servicio mostrador',
        'slug' => 'servicio-mostrador-'.$business->id,
        'unit_type' => 'unit',
        'sale_price' => 1000,
        'cost_price' => 0,
        'stock' => 10,
        'min_stock' => 0,
        'is_active' => true,
    ]);

    return [$business, $admin, $product];
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

test('sale creation auto issues fiscal document when fiscal api authorizes', function () {
    [$business, $admin, $product] = fiscalSaleStoreFixture();

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/documents' => Http::response([
            'data' => [
                'id' => 91,
                'status' => 'authorized',
                'cae' => '12345678901234',
                'cae_expires_at' => '2026-05-01',
                'number' => 1,
                'point_of_sale' => 2,
                'cbte_type' => 11,
            ],
        ], 201),
    ]);

    $this
        ->actingAs($admin)
        ->post('/sales', [
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'amount_received' => 1000,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 1000,
            ]],
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Venta registrada y comprobante fiscal autorizado correctamente.')
        ->assertSessionMissing('warning');

    $sale = Sale::query()->firstOrFail();
    $document = SaleFiscalDocument::query()->firstOrFail();

    expect($document->sale_id)->toBe($sale->id)
        ->and($document->business_id)->toBe($business->id)
        ->and($document->fiscal_status)->toBe(SaleFiscalDocument::STATUS_AUTHORIZED)
        ->and($document->fiscal_document_id)->toBe('91')
        ->and($document->fiscal_cae)->toBe('12345678901234');

    Http::assertSent(function (Request $request) use ($sale): bool {
        return $request->url() === 'http://127.0.0.1:8000/api/fiscal/documents'
            && $request->data()['invoice_mode'] === 'auto'
            && ! array_key_exists('cbte_type', $request->data())
            && $request->data()['origin_type'] === 'sale'
            && $request->data()['origin_id'] === (string) $sale->id
            && $request->data()['sale_id'] === 'S-000001';
    });
});

test('sale creation sends fiscal receiver data to API in auto invoice mode', function () {
    [$business, $admin, $product] = fiscalSaleStoreFixture([
        'fiscal_condition' => 'responsable_inscripto',
    ]);

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/documents' => Http::response([
            'data' => [
                'id' => 93,
                'status' => 'authorized',
                'cae' => '12345678901234',
                'cae_expires_at' => '2026-05-01',
                'number' => 1,
                'point_of_sale' => 2,
                'cbte_type' => 1,
            ],
        ], 201),
    ]);

    $this
        ->actingAs($admin)
        ->post('/sales', [
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'amount_received' => 1000,
            'fiscal_customer' => [
                'with_data' => true,
                'name' => 'Cliente Responsable SA',
                'document_type' => 'CUIT',
                'document_number' => '30-71234567-1',
                'iva_condition' => 'responsable_inscripto',
                'address' => 'Av. Fiscal 123',
            ],
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 1000,
            ]],
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Venta registrada y comprobante fiscal autorizado correctamente.');

    $sale = Sale::query()->firstOrFail();

    expect($sale->fiscal_customer)->toMatchArray([
        'with_data' => true,
        'name' => 'Cliente Responsable SA',
        'document_type' => 'CUIT',
        'document_number' => '30712345671',
        'iva_condition' => 'responsable_inscripto',
        'address' => 'Av. Fiscal 123',
    ]);
    expect((float) $sale->fiscal_net_amount)->toBe(826.45);
    expect((float) $sale->fiscal_vat_amount)->toBe(173.55);

    Http::assertSent(function (Request $request): bool {
        $payload = $request->data();

        return $payload['invoice_mode'] === 'auto'
            && ! array_key_exists('cbte_type', $payload)
            && $payload['customer']['name'] === 'Cliente Responsable SA'
            && $payload['customer']['document_type'] === 'CUIT'
            && $payload['customer']['document_number'] === '30712345671'
            && $payload['customer']['iva_condition'] === 'responsable_inscripto'
            && $payload['customer']['address'] === 'Av. Fiscal 123'
            && $payload['amounts']['imp_total'] === 1000.0
            && abs((float) $payload['amounts']['imp_neto'] - 826.45) < 0.001
            && abs((float) $payload['amounts']['imp_iva'] - 173.55) < 0.001
            && data_get($payload, 'amounts.iva_items.0.id') === 5
            && data_get($payload, 'amounts.iva_items.0.rate') === 21.0
            && abs((float) data_get($payload, 'amounts.iva_items.0.base_imp') - 826.45) < 0.001
            && abs((float) data_get($payload, 'amounts.iva_items.0.importe') - 173.55) < 0.001;
    });

    expect(SaleFiscalDocument::query()->firstOrFail()->fiscal_cbte_type)->toBe(1);
});

test('sale creation keeps sale and exposes manual fiscal retry when auto issue fails', function () {
    [, $admin, $product] = fiscalSaleStoreFixture();

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/documents' => Http::response([
            'data' => [
                'id' => 92,
                'status' => 'rejected',
                'error' => [
                    'code' => '10016',
                    'message' => 'Datos fiscales invalidos.',
                ],
            ],
        ], 201),
    ]);

    $this
        ->actingAs($admin)
        ->post('/sales', [
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'amount_received' => 1000,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 1000,
            ]],
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Venta registrada correctamente.')
        ->assertSessionHas('warning', 'Venta registrada, pero la API fiscal rechazo el comprobante. Revisar y reintentar desde el detalle de la venta.');

    $sale = Sale::query()->firstOrFail();
    $document = SaleFiscalDocument::query()->firstOrFail();

    expect($document->sale_id)->toBe($sale->id)
        ->and($document->fiscal_status)->toBe(SaleFiscalDocument::STATUS_REJECTED)
        ->and($document->fiscal_error_code)->toBe('10016');

    $this
        ->actingAs($admin)
        ->get(route('sales.show', $sale))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('fiscal.enabled', true)
            ->where('fiscal.can_issue', true)
            ->where('fiscal.document.fiscal_status', SaleFiscalDocument::STATUS_REJECTED)
        );
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
    expect($document->fiscal_error_message)->toBe('La API fiscal rechazo los datos del comprobante. Revisa importes, IVA, documento del receptor, tipo de comprobante y punto de venta. Detalle: Comprobante rechazado por validacion fiscal.');
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
        ->assertSessionHas('error', 'La API fiscal rechazo los datos del comprobante. Revisa importes, IVA, documento del receptor, tipo de comprobante y punto de venta. Detalle: La actividad seleccionada 492140 no se encuentra vinculada al emisor o no esta activa.');

    $document = SaleFiscalDocument::query()->firstOrFail();

    expect($document->fiscal_document_id)->toBe('9');
    expect($document->fiscal_status)->toBe(SaleFiscalDocument::STATUS_REJECTED);
    expect($document->fiscal_number)->toBe(1);
    expect($document->fiscal_error_code)->toBe('10223');
    expect($document->fiscal_error_message)->toBe('La API fiscal rechazo los datos del comprobante. Revisa importes, IVA, documento del receptor, tipo de comprobante y punto de venta. Detalle: La actividad seleccionada 492140 no se encuentra vinculada al emisor o no esta activa.');
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
    expect($document->fiscal_error_message)->toBe('La API fiscal no respondio a tiempo. El estado del comprobante quedo incierto. Usa Conciliar antes de reintentar.');
});

test('api fiscal 502 leaves fiscal document uncertain and blocks direct retry', function () {
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
        ->assertSessionHas('error', 'La API fiscal informo un error interno o de infraestructura. No se debe volver a emitir directamente. Usa Conciliar para verificar si el comprobante fue procesado.');

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

test('api fiscal 504 leaves fiscal document uncertain and asks for reconcile', function () {
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
        ->assertSessionHas('error', 'La API fiscal no respondio a tiempo. El estado del comprobante quedo incierto. Usa Conciliar antes de reintentar.');

    $document = SaleFiscalDocument::query()->firstOrFail();

    expect($document->fiscal_status)->toBe(SaleFiscalDocument::STATUS_UNCERTAIN);
    expect($document->fiscal_error_code)->toBe('http_504');
});

test('document without voucher number is shown as retryable instead of reconcilable', function () {
    [$business, $admin, $sale] = fiscalSaleFixture();

    SaleFiscalDocument::query()->create([
        'business_id' => $business->id,
        'sale_id' => $sale->id,
        'attempt_number' => 1,
        'fiscal_document_id' => 'fdoc-without-number',
        'fiscal_status' => SaleFiscalDocument::STATUS_UNCERTAIN,
        'fiscal_point_of_sale' => 2,
        'fiscal_cbte_type' => 11,
        'fiscal_error_code' => 'document_without_number',
        'fiscal_error_message' => 'Document has no voucher number to reconcile.',
        'fiscal_idempotency_key' => "sale:{$business->id}:{$sale->id}:invoice",
        'attempted_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->get(route('sales.show', $sale))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('fiscal.can_issue', true)
            ->where('fiscal.can_reconcile', false)
            ->where('fiscal.document.fiscal_status', SaleFiscalDocument::STATUS_UNCERTAIN)
            ->where('fiscal.document.fiscal_error_action', 'reintentar')
        );
});

test('reconcile document without voucher number converts the attempt to retryable error', function () {
    [$business, $admin, $sale] = fiscalSaleFixture();

    $document = SaleFiscalDocument::query()->create([
        'business_id' => $business->id,
        'sale_id' => $sale->id,
        'attempt_number' => 1,
        'fiscal_document_id' => 'fdoc-without-number',
        'fiscal_status' => SaleFiscalDocument::STATUS_UNCERTAIN,
        'fiscal_point_of_sale' => 2,
        'fiscal_cbte_type' => 11,
        'fiscal_idempotency_key' => "sale:{$business->id}:{$sale->id}:invoice",
        'attempted_at' => now(),
    ]);

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/documents/fdoc-without-number/reconcile' => Http::response([
            'message' => 'Document has no voucher number to reconcile.',
            'error_code' => 'document_without_number',
        ], 409),
    ]);

    $this
        ->actingAs($admin)
        ->post(route('sales.fiscal-documents.reconcile', [
            'sale' => $sale,
            'saleFiscalDocument' => $document,
        ]))
        ->assertRedirect()
        ->assertSessionHas('error');

    $document->refresh();

    expect($document->fiscal_status)->toBe(SaleFiscalDocument::STATUS_ERROR)
        ->and($document->fiscal_error_code)->toBe('document_without_number');

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/documents' => Http::response([
            'id' => 'fdoc-retry-after-without-number',
            'status' => 'authorized',
            'cae' => '52345678901234',
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

    expect($documents)->toHaveCount(2)
        ->and($documents[1]->attempt_number)->toBe(2)
        ->and($documents[1]->fiscal_status)->toBe(SaleFiscalDocument::STATUS_AUTHORIZED)
        ->and($documents[1]->fiscal_idempotency_key)->toBe("sale:{$business->id}:{$sale->id}:invoice:retry:2");
});

test('sale fiscal document emission stores caea authorization fields', function () {
    [$business, $admin, $sale] = fiscalSaleFixture([
        'fiscal_authorization_mode' => 'caea',
        'fiscal_caea_code' => '20260412345678',
        'fiscal_caea_period' => '202604',
        'fiscal_caea_order' => 2,
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
            && $request->data()['authorization_mode'] === 'caea'
            && $request->data()['authorization_type'] === 'CAEA'
            && data_get($request->data(), 'caea.code') === '20260412345678';
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
            && $payload['origin']['type'] === 'sale'
            && $payload['origin']['id'] === (string) $sale->id
            && $payload['invoice_mode'] === 'auto'
            && $payload['voucher_date'] === '2026-04-21'
            && $payload['point_of_sale'] === 2
            && ! array_key_exists('cbte_type', $payload)
            && $payload['concept'] === 2
            && $payload['customer']['document_type'] === 'CONSUMIDOR_FINAL'
            && $payload['customer']['document_number'] === '0'
            && $payload['customer']['iva_condition'] === 'consumidor_final'
            && $payload['amounts']['imp_total'] === 54562.74
            && $payload['amounts']['imp_neto'] === 54562.74
            && $payload['amounts']['imp_iva'] === 0.0
            && $payload['amounts']['iva_items'] === []
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

test('fiscal qr payload and url are generated for authorized cae document', function () {
    [$business, , $sale] = fiscalSaleFixture([
        'fiscal_cuit' => '20440587780',
    ]);

    $document = SaleFiscalDocument::query()->create([
        'business_id' => $business->id,
        'sale_id' => $sale->id,
        'attempt_number' => 1,
        'fiscal_document_id' => 'fdoc-qr-001',
        'fiscal_status' => SaleFiscalDocument::STATUS_AUTHORIZED,
        'fiscal_point_of_sale' => 2,
        'fiscal_cbte_type' => 11,
        'fiscal_number' => 2,
        'fiscal_cae' => '86173407873027',
        'fiscal_cae_expires_at' => '2026-05-01',
        'authorization_type' => SaleFiscalDocument::AUTHORIZATION_CAE,
        'authorization_code' => '86173407873027',
        'authorization_expires_at' => '2026-05-01',
        'fiscal_idempotency_key' => "sale:{$business->id}:{$sale->id}:invoice",
        'fiscal_payload' => [
            'voucher_date' => '2026-04-21',
            'currency' => 'PES',
            'currency_rate' => 1,
            'customer' => [
                'doc_type' => 99,
                'doc_number' => 0,
                'name' => 'Consumidor Final',
            ],
        ],
        'attempted_at' => now(),
        'authorized_at' => now(),
    ]);

    $service = app(FiscalQrService::class);
    $payload = $service->payload($document);
    $url = $service->url($document);

    expect($payload)->toMatchArray([
        'ver' => 1,
        'fecha' => '2026-04-21',
        'cuit' => 20440587780,
        'ptoVta' => 2,
        'tipoCmp' => 11,
        'nroCmp' => 2,
        'importe' => 54562.74,
        'moneda' => 'PES',
        'ctz' => 1.0,
        'tipoDocRec' => 99,
        'nroDocRec' => 0,
        'tipoCodAut' => 'E',
        'codAut' => 86173407873027,
    ]);

    expect($url)->toStartWith('https://www.arca.gob.ar/fe/qr/?p=');

    $decoded = json_decode(base64_decode(substr($url, strlen('https://www.arca.gob.ar/fe/qr/?p='))), true);

    expect($decoded)->toBe($payload);
});

test('fiscal qr payload uses caea authorization type', function () {
    [$business, , $sale] = fiscalSaleFixture([
        'fiscal_cuit' => '20440587780',
    ]);

    $document = SaleFiscalDocument::query()->create([
        'business_id' => $business->id,
        'sale_id' => $sale->id,
        'attempt_number' => 1,
        'fiscal_document_id' => 'fdoc-qr-caea',
        'fiscal_status' => SaleFiscalDocument::STATUS_AUTHORIZED,
        'fiscal_point_of_sale' => 2,
        'fiscal_cbte_type' => 11,
        'fiscal_number' => 3,
        'authorization_type' => SaleFiscalDocument::AUTHORIZATION_CAEA,
        'authorization_code' => '20260412345678',
        'authorization_expires_at' => '2026-05-15',
        'fiscal_idempotency_key' => "sale:{$business->id}:{$sale->id}:invoice",
        'fiscal_payload' => [
            'voucher_date' => '2026-04-21',
            'currency' => 'PES',
            'currency_rate' => 1,
        ],
        'attempted_at' => now(),
        'authorized_at' => now(),
    ]);

    expect(app(FiscalQrService::class)->payload($document)['tipoCodAut'])->toBe('A');
});

test('authorized fiscal document pdf can be downloaded', function () {
    [$business, $admin, $sale] = fiscalSaleFixture([
        'fiscal_cuit' => '20440587780',
    ]);

    $document = SaleFiscalDocument::query()->create([
        'business_id' => $business->id,
        'sale_id' => $sale->id,
        'attempt_number' => 1,
        'fiscal_document_id' => 'fdoc-pdf-001',
        'fiscal_status' => SaleFiscalDocument::STATUS_AUTHORIZED,
        'fiscal_point_of_sale' => 2,
        'fiscal_cbte_type' => 11,
        'fiscal_number' => 2,
        'fiscal_cae' => '86173407873027',
        'fiscal_cae_expires_at' => '2026-05-01',
        'authorization_type' => SaleFiscalDocument::AUTHORIZATION_CAE,
        'authorization_code' => '86173407873027',
        'authorization_expires_at' => '2026-05-01',
        'fiscal_idempotency_key' => "sale:{$business->id}:{$sale->id}:invoice",
        'fiscal_payload' => [
            'voucher_date' => '2026-04-21',
            'currency' => 'PES',
            'currency_rate' => 1,
            'customer' => [
                'doc_type' => 99,
                'doc_number' => 0,
                'name' => 'Consumidor Final',
            ],
        ],
        'attempted_at' => now(),
        'authorized_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->get(route('sales.fiscal-documents.pdf', [
            'sale' => $sale,
            'saleFiscalDocument' => $document,
        ]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('fiscal document pdf is blocked when document is not authorized', function () {
    [$business, $admin, $sale] = fiscalSaleFixture([
        'fiscal_cuit' => '20440587780',
    ]);

    $document = SaleFiscalDocument::query()->create([
        'business_id' => $business->id,
        'sale_id' => $sale->id,
        'attempt_number' => 1,
        'fiscal_status' => SaleFiscalDocument::STATUS_REJECTED,
        'fiscal_point_of_sale' => 2,
        'fiscal_cbte_type' => 11,
        'fiscal_number' => 2,
        'fiscal_error_code' => '10016',
        'fiscal_error_message' => 'Rechazado.',
        'fiscal_idempotency_key' => "sale:{$business->id}:{$sale->id}:invoice",
        'attempted_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->get(route('sales.fiscal-documents.pdf', [
            'sale' => $sale,
            'saleFiscalDocument' => $document,
        ]))
        ->assertStatus(422);
});
