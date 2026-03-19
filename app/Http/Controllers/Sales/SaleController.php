<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSaleRequest;
use App\Models\Business;
use App\Models\BusinessFeature;
use App\Models\Product;
use App\Models\Sale;
use App\Services\SaleService;
use App\Support\CurrentBusiness;
use App\Support\ProductMeasurement;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function __construct(private readonly SaleService $saleService)
    {
    }

    public function index(Request $request, CurrentBusiness $currentBusiness): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        $business->load([
            'features' => fn ($query) => $query->where('feature', BusinessFeature::ADVANCED_SALE_SETTINGS),
            'saleSectors' => fn ($query) => $query->orderBy('sort_order')->orderBy('name'),
            'paymentDestinations' => fn ($query) => $query->orderBy('sort_order')->orderBy('name'),
        ]);

        $advancedSaleSettingsEnabled = $business->hasAdvancedSaleSettings();
        $search = trim((string) $request->query('search', ''));
        $month = trim((string) $request->query('month', ''));
        $saleSectorId = $advancedSaleSettingsEnabled && $request->filled('sale_sector_id')
            ? (int) $request->query('sale_sector_id')
            : null;
        $paymentDestinationId = $advancedSaleSettingsEnabled && $request->filled('payment_destination_id')
            ? (int) $request->query('payment_destination_id')
            : null;
        [$monthStart, $monthEnd, $summaryMonth] = $this->resolveSummaryMonthRange($month);

        $sales = Sale::query()
            ->forBusiness($business->id)
            ->with(['user', 'saleSector', 'paymentDestination'])
            ->withCount('items')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery
                        ->where('sale_number', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            ->when($month !== '', function ($query) use ($monthStart, $monthEnd): void {
                $query->whereBetween('sold_at', [$monthStart, $monthEnd]);
            })
            ->when($advancedSaleSettingsEnabled && $saleSectorId !== null, function ($query) use ($saleSectorId): void {
                $query->where('sale_sector_id', $saleSectorId);
            })
            ->when($advancedSaleSettingsEnabled && $paymentDestinationId !== null, function ($query) use ($paymentDestinationId): void {
                $query->where('payment_destination_id', $paymentDestinationId);
            })
            ->latest('sold_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Sale $sale) => [
                'id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'payment_method' => $sale->payment_method,
                'sale_sector' => $sale->saleSector?->name,
                'payment_destination' => $sale->paymentDestination?->name,
                'subtotal' => (float) $sale->subtotal,
                'discount' => (float) $sale->discount,
                'total' => (float) $sale->total,
                'sold_at' => $sale->sold_at?->format('Y-m-d H:i'),
                'user' => $sale->user?->name,
                'items_count' => $sale->items_count,
            ]);

        $summaryBaseQuery = Sale::query()
            ->forBusiness($business->id)
            ->whereBetween('sold_at', [$monthStart, $monthEnd]);

        return Inertia::render('Sales/Index', [
            'filters' => [
                'search' => $search,
                'month' => $month,
                'sale_sector_id' => $saleSectorId,
                'payment_destination_id' => $paymentDestinationId,
            ],
            'sales' => $sales,
            'advanced_sale_settings' => [
                'enabled' => $advancedSaleSettingsEnabled,
                'sale_sectors' => $business->saleSectors
                    ->map(fn ($sector) => [
                        'id' => $sector->id,
                        'name' => $sector->name,
                        'is_active' => $sector->is_active,
                    ])
                    ->values()
                    ->all(),
                'payment_destinations' => $business->paymentDestinations
                    ->map(fn ($destination) => [
                        'id' => $destination->id,
                        'name' => $destination->name,
                        'is_active' => $destination->is_active,
                    ])
                    ->values()
                    ->all(),
            ],
            'monthly_summary' => [
                'month' => $summaryMonth,
                'sales_count' => (clone $summaryBaseQuery)->count(),
                'total' => (float) (clone $summaryBaseQuery)->sum('total'),
                'by_sector' => $advancedSaleSettingsEnabled
                    ? $this->salesTotalsBySector($business, $monthStart, $monthEnd)
                    : [],
                'by_payment_destination' => $advancedSaleSettingsEnabled
                    ? $this->salesTotalsByPaymentDestination($business, $monthStart, $monthEnd)
                    : [],
            ],
        ]);
    }

    public function create(CurrentBusiness $currentBusiness): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        return Inertia::render('Sales/Create', [
            'products' => $this->searchProductsPayload($business->id),
            'advanced_sale_settings' => $this->advancedSaleSettingsPayload($business),
        ]);
    }

    public function searchProducts(Request $request, CurrentBusiness $currentBusiness): JsonResponse
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        $search = trim((string) $request->query('search', ''));

        return response()->json([
            'products' => $this->searchProductsPayload($business->id, $search),
        ]);
    }

    public function store(
        StoreSaleRequest $request,
        CurrentBusiness $currentBusiness
    ): RedirectResponse {
        $business = $currentBusiness->get();
        $user = $request->user();

        abort_if($business === null || $user === null, 404);

        $sale = $this->saleService->createSale($business, $user, $request->validated());

        return redirect()
            ->route('sales.show', ['sale' => $sale, 'auto_back' => 1])
            ->with('success', 'Venta registrada correctamente.');
    }

    public function show(Request $request, CurrentBusiness $currentBusiness, Sale $sale): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);
        abort_if($sale->business_id !== $business->id, 403);

        $sale->load(['items.product', 'user']);
        $sale->loadMissing(['saleSector', 'paymentDestination']);

        return Inertia::render('Sales/Show', [
            'auto_back' => $request->boolean('auto_back'),
            'advanced_sale_settings_enabled' => $business->hasAdvancedSaleSettings(),
            'sale' => [
                'id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'payment_method' => $sale->payment_method,
                'sale_sector' => $sale->saleSector?->name,
                'payment_destination' => $sale->paymentDestination?->name,
                'amount_received' => (float) ($sale->amount_received ?? 0),
                'change_amount' => (float) ($sale->change_amount ?? 0),
                'subtotal' => (float) $sale->subtotal,
                'discount' => (float) $sale->discount,
                'total' => (float) $sale->total,
                'notes' => $sale->notes,
                'sold_at' => $sale->sold_at?->format('Y-m-d H:i'),
                'user' => $sale->user?->name,
                'items' => $sale->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'subtotal' => (float) $item->subtotal,
                    'quantity_label' => ProductMeasurement::quantityLabel($item->product?->unit_type, $item->product?->weight_unit),
                    'price_label' => ProductMeasurement::priceLabel($item->product?->unit_type, $item->product?->weight_unit),
                ]),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function advancedSaleSettingsPayload(Business $business): array
    {
        $business->load([
            'features' => fn ($query) => $query->where('feature', BusinessFeature::ADVANCED_SALE_SETTINGS),
            'saleSectors' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('name'),
            'paymentDestinations' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('name'),
        ]);

        return [
            'enabled' => $business->hasAdvancedSaleSettings(),
            'sale_sectors' => $business->saleSectors->map(fn ($sector) => [
                'id' => $sector->id,
                'name' => $sector->name,
                'description' => $sector->description,
            ])->values()->all(),
            'payment_destinations' => $business->paymentDestinations->map(fn ($destination) => [
                'id' => $destination->id,
                'name' => $destination->name,
                'account_holder' => $destination->account_holder,
                'reference' => $destination->reference,
                'account_number' => $destination->account_number,
            ])->values()->all(),
        ];
    }

    /**
     * @return array{0: \Carbon\CarbonImmutable, 1: \Carbon\CarbonImmutable, 2: string}
     */
    private function resolveSummaryMonthRange(string $month): array
    {
        $targetMonth = preg_match('/^\d{4}-\d{2}$/', $month) === 1
            ? CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();

        return [$targetMonth, $targetMonth->endOfMonth(), $targetMonth->format('Y-m')];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function salesTotalsBySector(Business $business, CarbonImmutable $monthStart, CarbonImmutable $monthEnd): array
    {
        $totals = Sale::query()
            ->forBusiness($business->id)
            ->select('sale_sector_id', DB::raw('SUM(total) as total'), DB::raw('COUNT(*) as sales_count'))
            ->whereBetween('sold_at', [$monthStart, $monthEnd])
            ->whereNotNull('sale_sector_id')
            ->groupBy('sale_sector_id')
            ->get()
            ->keyBy('sale_sector_id');

        return $business->saleSectors
            ->map(function ($sector) use ($totals): array {
                $row = $totals->get($sector->id);

                return [
                    'id' => $sector->id,
                    'name' => $sector->name,
                    'is_active' => $sector->is_active,
                    'total' => (float) ($row?->total ?? 0),
                    'sales_count' => (int) ($row?->sales_count ?? 0),
                ];
            })
            ->filter(fn (array $row): bool => $row['sales_count'] > 0 || $row['is_active'])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function salesTotalsByPaymentDestination(Business $business, CarbonImmutable $monthStart, CarbonImmutable $monthEnd): array
    {
        $totals = Sale::query()
            ->forBusiness($business->id)
            ->select('payment_destination_id', DB::raw('SUM(total) as total'), DB::raw('COUNT(*) as sales_count'))
            ->whereBetween('sold_at', [$monthStart, $monthEnd])
            ->whereNotNull('payment_destination_id')
            ->groupBy('payment_destination_id')
            ->get()
            ->keyBy('payment_destination_id');

        return $business->paymentDestinations
            ->map(function ($destination) use ($totals): array {
                $row = $totals->get($destination->id);

                return [
                    'id' => $destination->id,
                    'name' => $destination->name,
                    'is_active' => $destination->is_active,
                    'total' => (float) ($row?->total ?? 0),
                    'sales_count' => (int) ($row?->sales_count ?? 0),
                ];
            })
            ->filter(fn (array $row): bool => $row['sales_count'] > 0 || $row['is_active'])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchProductsPayload(int $businessId, string $search = ''): array
    {
        $limit = $search === '' ? 20 : 30;

        return $this->productSearchQuery($businessId, $search)
            ->limit($limit)
            ->get()
            ->map(fn (Product $product) => $this->mapProduct($product))
            ->values()
            ->all();
    }

    /**
     * @return Builder<Product>
     */
    private function productSearchQuery(int $businessId, string $search): Builder
    {
        $query = Product::query()
            ->forBusiness($businessId)
            ->where('is_active', true);

        if ($search === '') {
            return $query->orderBy('name');
        }

        return $query
            ->where(function (Builder $innerQuery) use ($search): void {
                $innerQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            })
            ->orderByRaw(
                "case
                    when barcode = ? or sku = ? then 0
                    when name like ? then 1
                    else 2
                end",
                [$search, $search, $search.'%']
            )
            ->orderBy('name');
    }

    /**
     * @return array<string, mixed>
     */
    private function mapProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'barcode' => $product->barcode,
            'sku' => $product->sku,
            'unit_type' => $product->unit_type,
            'weight_unit' => $product->weight_unit,
            'quantity_label' => ProductMeasurement::quantityLabel($product->unit_type, $product->weight_unit),
            'price_label' => ProductMeasurement::priceLabel($product->unit_type, $product->weight_unit),
            'quantity_step' => ProductMeasurement::quantityStep($product->unit_type, $product->weight_unit),
            'quantity_min' => ProductMeasurement::quantityMin($product->unit_type, $product->weight_unit),
            'sale_price' => (float) $product->sale_price,
            'stock' => (float) $product->stock,
        ];
    }
}
