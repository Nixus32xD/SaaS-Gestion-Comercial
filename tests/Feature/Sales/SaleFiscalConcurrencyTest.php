<?php

use App\Models\Business;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleFiscalDocument;
use App\Models\User;
use App\Services\Fiscal\FiscalSaleDocumentService;
use App\Services\Fiscal\LockedFiscalSaleDocumentService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    config()->set('fiscal.enabled', true);
    config()->set('fiscal.base_url', 'http://127.0.0.1:8000/api');
    config()->set('fiscal.token', 'testing-fiscal-token');
    config()->set('fiscal.defaults.point_of_sale', 2);
    config()->set('fiscal.defaults.cbte_type', 11);
    config()->set('fiscal.defaults.concept', 1);
    config()->set('fiscal.defaults.activities', []);
});

it('reserves only one fiscal attempt when a second request arrives during the first HTTP call', function (): void {
    [$business, $admin, $product, $sale] = concurrencySaleFixture();
    $service = app(FiscalSaleDocumentService::class);
    $secondRequestWasBlocked = false;

    expect($service)->toBeInstanceOf(LockedFiscalSaleDocumentService::class);

    Http::fake(function (Request $request) use ($service, $sale, &$secondRequestWasBlocked) {
        try {
            // The first service call has already committed the processing row at
            // this point. This simulates a concurrent request while apiArca is in flight.
            $service->issue($sale->fresh());
        } catch (ValidationException) {
            $secondRequestWasBlocked = true;
        }

        return Http::response([
            'data' => [
                'id' => 701,
                'status' => 'authorized',
                'cae' => '12345678901234',
                'cae_expires_at' => '2026-09-10',
                'number' => 31,
                'point_of_sale' => 2,
                'cbte_type' => 11,
            ],
        ], 201);
    });

    $document = $service->issue($sale);

    expect($secondRequestWasBlocked)->toBeTrue()
        ->and($document->fiscal_status)->toBe(SaleFiscalDocument::STATUS_AUTHORIZED)
        ->and(SaleFiscalDocument::query()->where('sale_id', $sale->id)->count())->toBe(1)
        ->and(SaleFiscalDocument::query()->where('sale_id', $sale->id)->value('attempt_number'))->toBe(1);

    Http::assertSentCount(1);
});

it('does not issue a sale that already has an authorized fiscal document', function (): void {
    [, , , $sale] = concurrencySaleFixture();
    concurrencyFiscalDocument($sale, SaleFiscalDocument::STATUS_AUTHORIZED, 1, [
        'fiscal_cae' => '12345678901234',
        'fiscal_number' => 30,
        'authorized_at' => now(),
    ]);

    Http::fake();

    expect(fn () => app(FiscalSaleDocumentService::class)->issue($sale->fresh()))
        ->toThrow(ValidationException::class);

    Http::assertNothingSent();
    expect($sale->fiscalDocuments()->count())->toBe(1);
});

it('does not create another attempt while the sale is uncertain', function (): void {
    [, , , $sale] = concurrencySaleFixture();
    concurrencyFiscalDocument($sale, SaleFiscalDocument::STATUS_UNCERTAIN, 1, [
        'fiscal_error_code' => 'arca_timeout',
        'fiscal_error_message' => 'Resultado incierto.',
        'fiscal_number' => 30,
    ]);

    Http::fake();

    expect(fn () => app(FiscalSaleDocumentService::class)->issue($sale->fresh()))
        ->toThrow(ValidationException::class);

    Http::assertNothingSent();
    expect($sale->fiscalDocuments()->count())->toBe(1);
});

it('keeps the uncertain state from apiArca and blocks a blind new emission', function (): void {
    [, , , $sale] = concurrencySaleFixture();

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/documents' => Http::response([
            'data' => [
                'id' => 702,
                'status' => 'uncertain',
                'number' => 31,
                'point_of_sale' => 2,
                'cbte_type' => 11,
                'error' => [
                    'code' => 'arca_timeout',
                    'message' => 'No se sabe si ARCA proceso el comprobante.',
                ],
            ],
        ], 201),
    ]);

    $service = app(FiscalSaleDocumentService::class);
    $document = $service->issue($sale);

    expect($document->fiscal_status)->toBe(SaleFiscalDocument::STATUS_UNCERTAIN)
        ->and($document->fiscal_number)->toBe(31);

    expect(fn () => $service->issue($sale->fresh()))
        ->toThrow(ValidationException::class);

    expect($sale->fiscalDocuments()->count())->toBe(1);
    Http::assertSentCount(1);
});

it('allows different sales of the same business to reach apiArca independently', function (): void {
    [$business, $admin, $product, $saleA] = concurrencySaleFixture();
    $saleB = concurrencyCreateSale($business, $admin, $product, 'S-000002');
    $nextDocument = 800;

    Http::fake(function () use (&$nextDocument) {
        $nextDocument++;

        return Http::response([
            'data' => [
                'id' => $nextDocument,
                'status' => 'authorized',
                'cae' => '12345678901234',
                'cae_expires_at' => '2026-09-10',
                'number' => $nextDocument,
                'point_of_sale' => 2,
                'cbte_type' => 11,
            ],
        ], 201);
    });

    $service = app(FiscalSaleDocumentService::class);
    $service->issue($saleA);
    $service->issue($saleB);

    expect(SaleFiscalDocument::query()->count())->toBe(2)
        ->and($saleA->fiscalDocuments()->count())->toBe(1)
        ->and($saleB->fiscalDocuments()->count())->toBe(1);

    Http::assertSentCount(2);
});

it('sends business scope when reconciling an apiArca document by id', function (): void {
    [$business, , , $sale] = concurrencySaleFixture();
    $document = concurrencyFiscalDocument($sale, SaleFiscalDocument::STATUS_UNCERTAIN, 1, [
        'fiscal_document_id' => '912',
        'fiscal_number' => 31,
        'fiscal_error_code' => 'arca_timeout',
    ]);

    Http::fake([
        'http://127.0.0.1:8000/api/fiscal/documents/912/reconcile' => Http::response([
            'data' => [
                'id' => 912,
                'status' => 'authorized',
                'cae' => '12345678901234',
                'cae_expires_at' => '2026-09-10',
                'number' => 31,
                'point_of_sale' => 2,
                'cbte_type' => 11,
            ],
        ]),
    ]);

    app(FiscalSaleDocumentService::class)->reconcile($document);

    Http::assertSent(function (Request $request) use ($business): bool {
        return $request->url() === 'http://127.0.0.1:8000/api/fiscal/documents/912/reconcile'
            && $request->data()['business_id'] === $business->fiscal_external_business_id;
    });
});

function concurrencySaleFixture(): array
{
    $business = Business::factory()->create([
        'fiscal_enabled' => true,
        'fiscal_external_business_id' => 'empresa-concurrency-prod',
        'fiscal_point_of_sale' => 2,
        'fiscal_document_type' => 'invoice_c',
        'fiscal_cbte_type' => 11,
        'fiscal_concept' => 1,
        'fiscal_activities' => [492140],
    ]);

    $admin = User::factory()->businessAdmin($business->id)->create();

    $product = Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Servicio concurrencia',
        'slug' => 'servicio-concurrencia-'.$business->id,
        'unit_type' => 'unit',
        'sale_price' => 1000,
        'cost_price' => 0,
        'stock' => 10,
        'min_stock' => 0,
        'is_active' => true,
    ]);

    $sale = concurrencyCreateSale($business, $admin, $product, 'S-000001');

    return [$business, $admin, $product, $sale];
}

function concurrencyCreateSale(Business $business, User $admin, Product $product, string $saleNumber): Sale
{
    $sale = Sale::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'sale_number' => $saleNumber,
        'payment_method' => 'transfer',
        'subtotal' => 1000,
        'discount' => 0,
        'total' => 1000,
        'sold_at' => '2026-08-31 10:00:00',
    ]);

    $sale->items()->create([
        'business_id' => $business->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 1,
        'unit_price' => 1000,
        'subtotal' => 1000,
    ]);

    return $sale;
}

function concurrencyFiscalDocument(
    Sale $sale,
    string $status,
    int $attemptNumber,
    array $overrides = [],
): SaleFiscalDocument {
    return SaleFiscalDocument::query()->create([
        'business_id' => $sale->business_id,
        'sale_id' => $sale->id,
        'attempt_number' => $attemptNumber,
        'fiscal_status' => $status,
        'fiscal_point_of_sale' => 2,
        'fiscal_cbte_type' => 11,
        'fiscal_idempotency_key' => $attemptNumber === 1
            ? "sale:{$sale->business_id}:{$sale->id}:invoice"
            : "sale:{$sale->business_id}:{$sale->id}:invoice:retry:{$attemptNumber}",
        'fiscal_payload' => [],
        'attempted_at' => now(),
        ...$overrides,
    ]);
}
