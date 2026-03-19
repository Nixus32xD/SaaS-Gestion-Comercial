<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\BusinessFeature;
use App\Models\BusinessPaymentDestination;
use App\Models\BusinessSaleSector;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Services\ProductExpirationAlertService;
use App\Support\CurrentBusiness;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(CurrentBusiness $currentBusiness, ProductExpirationAlertService $expirationAlertService): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        $business->load([
            'features' => fn ($query) => $query->where('feature', BusinessFeature::ADVANCED_SALE_SETTINGS),
        ]);

        $advancedSaleSettingsEnabled = $business->hasAdvancedSaleSettings();
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $salesSummary = Sale::query()
            ->forBusiness($business->id)
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN sold_at BETWEEN ? AND ? THEN total ELSE 0 END), 0) as today_sales, COALESCE(SUM(CASE WHEN sold_at BETWEEN ? AND ? THEN total ELSE 0 END), 0) as month_sales',
                [$todayStart, $todayEnd, $monthStart, $monthEnd]
            )
            ->first();

        $todaySales = (float) ($salesSummary?->today_sales ?? 0);
        $monthSales = (float) ($salesSummary?->month_sales ?? 0);

        $productsCount = Product::query()->forBusiness($business->id)->count();
        $suppliersCount = Supplier::query()->forBusiness($business->id)->count();

        $lowStock = Product::query()
            ->forBusiness($business->id)
            ->select(['id', 'name', 'stock', 'min_stock'])
            ->whereColumn('stock', '<=', 'min_stock')
            ->orderBy('stock')
            ->limit(8)
            ->get();

        $topProducts = SaleItem::query()
            ->select([
                'sale_items.product_id',
                'sale_items.product_name',
                'products.unit_type',
                'products.weight_unit',
                DB::raw("
                    SUM(
                        CASE
                            WHEN products.unit_type = 'weight' AND products.weight_unit = 'g'
                                THEN sale_items.quantity / 1000
                            ELSE sale_items.quantity
                        END
                    ) as sold_quantity
                "),
            ])
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->forBusiness($business->id)
            ->groupBy('sale_items.product_id', 'sale_items.product_name', 'products.unit_type', 'products.weight_unit')
            ->orderByDesc('sold_quantity')
            ->limit(8)
            ->get();

        $latestSales = Sale::query()
            ->forBusiness($business->id)
            ->select(['id', 'business_id', 'user_id', 'sale_sector_id', 'sale_number', 'payment_destination_id', 'total', 'sold_at'])
            ->with(['user:id,name', 'saleSector:id,name', 'paymentDestination:id,name'])
            ->latest('sold_at')
            ->limit(8)
            ->get();

        $latestPurchases = Purchase::query()
            ->forBusiness($business->id)
            ->select(['id', 'business_id', 'supplier_id', 'purchase_number', 'total', 'purchased_at'])
            ->with('supplier:id,name')
            ->latest('purchased_at')
            ->limit(8)
            ->get();

        $expirationAlerts = $expirationAlertService->listForBusiness($business->id, 8)->values();

        $trendStart = now()->startOfDay()->subDays(13);
        $trendEnd = now()->endOfDay();

        $salesByDate = Sale::query()
            ->forBusiness($business->id)
            ->selectRaw('DATE(sold_at) as day, SUM(total) as total')
            ->whereBetween('sold_at', [$trendStart, $trendEnd])
            ->groupBy('day')
            ->pluck('total', 'day');

        $purchasesByDate = Purchase::query()
            ->forBusiness($business->id)
            ->selectRaw('DATE(purchased_at) as day, SUM(total) as total')
            ->whereBetween('purchased_at', [$trendStart, $trendEnd])
            ->groupBy('day')
            ->pluck('total', 'day');

        $dailyTotals = collect(range(0, 13))->map(function (int $offset) use ($trendStart, $salesByDate, $purchasesByDate): array {
            $date = $trendStart->copy()->addDays($offset)->toDateString();

            return [
                'date' => $date,
                'sales_total' => (float) ($salesByDate->get($date) ?? 0),
                'purchases_total' => (float) ($purchasesByDate->get($date) ?? 0),
            ];
        });

        return Inertia::render('Dashboard/Index', [
            'summary' => [
                'today_sales' => $todaySales,
                'month_sales' => $monthSales,
                'products_count' => $productsCount,
                'suppliers_count' => $suppliersCount,
            ],
            'daily_totals' => $dailyTotals->all(),
            'low_stock_products' => $lowStock->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'stock' => (float) $product->stock,
                'min_stock' => (float) $product->min_stock,
            ]),
            'top_sold_products' => $topProducts->map(fn ($row) => [
                'product_id' => $row->product_id,
                'product_name' => $row->product_name,
                'sold_quantity' => round((float) $row->sold_quantity, 3),
                'sold_quantity_label' => $row->unit_type === 'weight' ? 'kg' : 'u',
            ]),
            'latest_sales' => $latestSales->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'total' => (float) $sale->total,
                'sold_at' => $sale->sold_at?->format('Y-m-d H:i'),
                'user' => $sale->user?->name,
                'sale_sector' => $sale->saleSector?->name,
                'payment_destination' => $sale->paymentDestination?->name,
            ]),
            'latest_purchases' => $latestPurchases->map(fn (Purchase $purchase) => [
                'id' => $purchase->id,
                'purchase_number' => $purchase->purchase_number,
                'total' => (float) $purchase->total,
                'purchased_at' => $purchase->purchased_at?->format('Y-m-d H:i'),
                'supplier' => $purchase->supplier?->name,
            ]),
            'expiration_alerts' => $expirationAlerts->all(),
            'advanced_sales' => [
                'enabled' => $advancedSaleSettingsEnabled,
                'month' => $monthStart->format('Y-m'),
                'sales_by_sector' => $advancedSaleSettingsEnabled
                    ? $this->salesBySector($business->id, $monthStart, $monthEnd)
                    : [],
                'sales_by_payment_destination' => $advancedSaleSettingsEnabled
                    ? $this->salesByPaymentDestination($business->id, $monthStart, $monthEnd)
                    : [],
            ],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function salesBySector(int $businessId, mixed $monthStart, mixed $monthEnd): array
    {
        $totals = Sale::query()
            ->forBusiness($businessId)
            ->select('sale_sector_id', DB::raw('SUM(total) as total'), DB::raw('COUNT(*) as sales_count'))
            ->whereBetween('sold_at', [$monthStart, $monthEnd])
            ->whereNotNull('sale_sector_id')
            ->groupBy('sale_sector_id')
            ->get()
            ->keyBy('sale_sector_id');

        return BusinessSaleSector::query()
            ->forBusiness($businessId)
            ->select(['id', 'name', 'is_active'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (BusinessSaleSector $sector) use ($totals): array {
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
    private function salesByPaymentDestination(int $businessId, mixed $monthStart, mixed $monthEnd): array
    {
        $totals = Sale::query()
            ->forBusiness($businessId)
            ->select('payment_destination_id', DB::raw('SUM(total) as total'), DB::raw('COUNT(*) as sales_count'))
            ->whereBetween('sold_at', [$monthStart, $monthEnd])
            ->whereNotNull('payment_destination_id')
            ->groupBy('payment_destination_id')
            ->get()
            ->keyBy('payment_destination_id');

        return BusinessPaymentDestination::query()
            ->forBusiness($businessId)
            ->select(['id', 'name', 'is_active'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (BusinessPaymentDestination $destination) use ($totals): array {
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
}
