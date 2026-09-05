<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\BusinessPaymentDestination;
use App\Models\BusinessSaleSector;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Services\BranchCommercialSettingsResolver;
use App\Services\CashRegisterService;
use App\Services\LowStockAlertService;
use App\Services\OperationalMonitoringService;
use App\Services\ProductExpirationAlertService;
use App\Support\CurrentBranch;
use App\Support\CurrentBusiness;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(
        Request $request,
        CurrentBusiness $currentBusiness,
        CurrentBranch $currentBranch,
        ProductExpirationAlertService $expirationAlertService,
        LowStockAlertService $lowStockAlertService,
        OperationalMonitoringService $operationalMonitoringService,
        BranchCommercialSettingsResolver $commercialSettingsResolver,
        CashRegisterService $cashRegisterService,
    ): Response {
        $business = $currentBusiness->get();
        $branch = $currentBranch->get();
        abort_if($business === null || $branch === null, 404);
        $branchScope = $request->query('branch_scope') === 'current' ? 'current' : 'all';
        $branchId = $branchScope === 'current' ? $branch->id : null;

        $advancedSaleSettingsEnabled = $commercialSettingsResolver
            ->advancedSaleSettingsEnabled($business, $branch);
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $yearStart = now()->startOfYear();

        $salesSummary = Sale::query()
            ->forBusiness($business->id)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN sold_at BETWEEN ? AND ? THEN total ELSE 0 END), 0) as today_sales, COALESCE(SUM(CASE WHEN sold_at BETWEEN ? AND ? THEN total ELSE 0 END), 0) as month_sales',
                [$todayStart, $todayEnd, $monthStart, $monthEnd]
            )
            ->first();

        $todaySales = (float) ($salesSummary?->today_sales ?? 0);
        $monthSales = (float) ($salesSummary?->month_sales ?? 0);

        $productsCount = Product::query()->forBusiness($business->id)->count();
        $suppliersCount = Supplier::query()->forBusiness($business->id)->count();

        $lowStock = $lowStockAlertService->listForBusiness($business->id, 8, $branchId);

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
            ->when($branchId !== null, fn ($query) => $query
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->where('sales.branch_id', $branchId))
            ->whereNotNull('sale_items.product_id')
            ->groupBy('sale_items.product_id', 'sale_items.product_name', 'products.unit_type', 'products.weight_unit')
            ->orderByDesc('sold_quantity')
            ->limit(8)
            ->get();

        $latestSales = Sale::query()
            ->forBusiness($business->id)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->select(['id', 'business_id', 'user_id', 'sale_sector_id', 'sale_number', 'payment_destination_id', 'total', 'sold_at'])
            ->with(['user:id,name', 'saleSector:id,name', 'paymentDestination:id,name'])
            ->latest('sold_at')
            ->limit(8)
            ->get();

        $latestPurchases = Purchase::query()
            ->forBusiness($business->id)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->select(['id', 'business_id', 'supplier_id', 'purchase_number', 'total', 'purchased_at'])
            ->with('supplier:id,name')
            ->latest('purchased_at')
            ->limit(8)
            ->get();

        $expirationAlerts = $expirationAlertService->listForBusiness($business->id, 8, null, $branchId)->values();

        $trendStart = now()->startOfDay()->subDays(13);
        $trendEnd = now()->endOfDay();

        $salesByDate = Sale::query()
            ->forBusiness($business->id)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->selectRaw('DATE(sold_at) as day, SUM(total) as total')
            ->whereBetween('sold_at', [$trendStart, $trendEnd])
            ->groupBy('day')
            ->pluck('total', 'day');

        $purchasesByDate = Purchase::query()
            ->forBusiness($business->id)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
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

        $allTimeStart = $this->firstActivityStart($business->id, $branchId);
        $cashSummary = $cashRegisterService->currentSummary($business, $branch);

        return Inertia::render('Dashboard/Index', [
            'summary' => [
                'today_sales' => $todaySales,
                'month_sales' => $monthSales,
                'products_count' => $productsCount,
                'suppliers_count' => $suppliersCount,
            ],
            'historical_summary' => [
                'periods' => [
                    $this->periodSummary(
                        $business->id,
                        'last_14_days',
                        'Ultimos 14 dias',
                        $trendStart,
                        $trendEnd,
                        $branchId,
                    ),
                    $this->periodSummary(
                        $business->id,
                        'current_month',
                        'Mes actual',
                        $monthStart,
                        $todayEnd,
                        $branchId,
                    ),
                    $this->periodSummary(
                        $business->id,
                        'current_year',
                        'Anio en curso',
                        $yearStart,
                        $todayEnd,
                        $branchId,
                    ),
                    $this->periodSummary(
                        $business->id,
                        'all_time',
                        'Historico total',
                        null,
                        null,
                        $branchId,
                    ),
                ],
            ],
            'performance_series' => [
                'periods' => [
                    $this->periodSeries($business->id, 'last_14_days', '14 dias', $trendStart, $trendEnd, 'day', $branchId),
                    $this->periodSeries($business->id, 'current_month', 'Mes', $monthStart, $todayEnd, 'day', $branchId),
                    $this->periodSeries($business->id, 'current_year', 'Anio', $yearStart, $todayEnd, 'month', $branchId),
                    $this->periodSeries($business->id, 'all_time', 'Total', $allTimeStart, $todayEnd, 'month', $branchId),
                ],
            ],
            'daily_totals' => $dailyTotals->all(),
            'low_stock_products' => $lowStock->map(fn (array $stock) => [
                'id' => $stock['branch_product_stock_id'],
                'name' => $stock['product_name'],
                'branch_name' => $stock['branch_name'],
                'stock' => $stock['stock'],
                'reserved_stock' => $stock['reserved_stock'],
                'available_stock' => $stock['available_stock'],
                'min_stock' => $stock['min_stock'],
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
            'operational_monitoring' => $operationalMonitoringService->forBusiness($business, $branchId),
            'branch_filter' => [
                'scope' => $branchScope,
                'current_branch_name' => $branch->name,
            ],
            // Caja is always branch-local, even when the commercial dashboard is consolidated.
            'cash_register' => [
                'is_open' => $cashSummary['session'] !== null,
                'branch_name' => $branch->name,
                'expected_amount' => $cashSummary['expected_amount'],
            ],
            'advanced_sales' => [
                'enabled' => $advancedSaleSettingsEnabled,
                'month' => $monthStart->format('Y-m'),
                'sales_by_sector' => $advancedSaleSettingsEnabled
                    ? $this->salesBySector($business->id, $monthStart, $monthEnd, $branchId)
                    : [],
                'sales_by_payment_destination' => $advancedSaleSettingsEnabled
                    ? $this->salesByPaymentDestination($business->id, $monthStart, $monthEnd, $branchId)
                    : [],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function periodSummary(
        int $businessId,
        string $key,
        string $label,
        mixed $start = null,
        mixed $end = null,
        ?int $branchId = null,
    ): array {
        $salesQuery = Sale::query()->forBusiness($businessId)->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId));
        $purchasesQuery = Purchase::query()->forBusiness($businessId)->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId));

        if ($start !== null && $end !== null) {
            $salesQuery->whereBetween('sold_at', [$start, $end]);
            $purchasesQuery->whereBetween('purchased_at', [$start, $end]);
        }

        $sales = $salesQuery
            ->selectRaw('COUNT(*) as sales_count, COALESCE(SUM(total), 0) as sales_total, COALESCE(AVG(total), 0) as average_ticket')
            ->first();

        $purchases = $purchasesQuery
            ->selectRaw('COUNT(*) as purchases_count, COALESCE(SUM(total), 0) as purchases_total')
            ->first();

        $salesTotal = (float) ($sales?->sales_total ?? 0);
        $purchasesTotal = (float) ($purchases?->purchases_total ?? 0);

        return [
            'key' => $key,
            'label' => $label,
            'range_label' => $start !== null && $end !== null
                ? $start->format('d/m/Y').' - '.$end->format('d/m/Y')
                : 'Desde el inicio',
            'sales_total' => $salesTotal,
            'sales_count' => (int) ($sales?->sales_count ?? 0),
            'purchases_total' => $purchasesTotal,
            'purchases_count' => (int) ($purchases?->purchases_count ?? 0),
            'net_total' => round($salesTotal - $purchasesTotal, 2),
            'average_ticket' => round((float) ($sales?->average_ticket ?? 0), 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function periodSeries(
        int $businessId,
        string $key,
        string $label,
        Carbon $start,
        Carbon $end,
        string $granularity,
        ?int $branchId = null,
    ): array {
        $salesTotals = $this->totalsByBucket(Sale::class, $businessId, 'sold_at', $start, $end, $granularity, $branchId);
        $purchaseTotals = $this->totalsByBucket(Purchase::class, $businessId, 'purchased_at', $start, $end, $granularity, $branchId);

        $points = $this->seriesBuckets($start, $end, $granularity)
            ->map(function (Carbon $bucket) use ($granularity, $salesTotals, $purchaseTotals): array {
                $bucketKey = $granularity === 'month'
                    ? $bucket->format('Y-m-01')
                    : $bucket->toDateString();

                $salesTotal = (float) ($salesTotals->get($bucketKey) ?? 0);
                $purchasesTotal = (float) ($purchaseTotals->get($bucketKey) ?? 0);

                return [
                    'bucket' => $bucketKey,
                    'label' => $granularity === 'month'
                        ? $bucket->format('m/Y')
                        : $bucket->format('d/m'),
                    'sales_total' => $salesTotal,
                    'purchases_total' => $purchasesTotal,
                    'net_total' => round($salesTotal - $purchasesTotal, 2),
                ];
            })
            ->values()
            ->all();

        return [
            'key' => $key,
            'label' => $label,
            'granularity' => $granularity,
            'range_label' => $start->format('d/m/Y').' - '.$end->format('d/m/Y'),
            'points' => $points,
        ];
    }

    /**
     * @param  class-string  $model
     * @return Collection<string, float>
     */
    private function totalsByBucket(
        string $model,
        int $businessId,
        string $dateColumn,
        Carbon $start,
        Carbon $end,
        string $granularity,
        ?int $branchId = null,
    ): Collection {
        $bucketExpression = $granularity === 'month'
            ? $this->monthBucketExpression($dateColumn)
            : "DATE({$dateColumn})";

        return $model::query()
            ->forBusiness($businessId)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->selectRaw("{$bucketExpression} as bucket, COALESCE(SUM(total), 0) as total")
            ->whereBetween($dateColumn, [$start, $end])
            ->groupBy(DB::raw($bucketExpression))
            ->pluck('total', 'bucket')
            ->map(fn ($total): float => (float) $total);
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function seriesBuckets(Carbon $start, Carbon $end, string $granularity): Collection
    {
        $buckets = collect();
        $cursor = $granularity === 'month'
            ? $start->copy()->startOfMonth()
            : $start->copy()->startOfDay();
        $last = $granularity === 'month'
            ? $end->copy()->startOfMonth()
            : $end->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($last)) {
            $buckets->push($cursor->copy());
            $granularity === 'month' ? $cursor->addMonth() : $cursor->addDay();
        }

        return $buckets;
    }

    private function monthBucketExpression(string $dateColumn): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m-01', {$dateColumn})",
            'pgsql' => "TO_CHAR({$dateColumn}, 'YYYY-MM-01')",
            default => "DATE_FORMAT({$dateColumn}, '%Y-%m-01')",
        };
    }

    private function firstActivityStart(int $businessId, ?int $branchId = null): Carbon
    {
        $firstDate = collect([
            Sale::query()->forBusiness($businessId)->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))->min('sold_at'),
            Purchase::query()->forBusiness($businessId)->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))->min('purchased_at'),
        ])
            ->filter()
            ->map(fn ($date): Carbon => Carbon::parse($date))
            ->sortBy(fn (Carbon $date): int => $date->timestamp)
            ->first();

        return $firstDate?->copy()->startOfMonth() ?? now()->startOfMonth();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function salesBySector(int $businessId, mixed $monthStart, mixed $monthEnd, ?int $branchId = null): array
    {
        $totals = Sale::query()
            ->forBusiness($businessId)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
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
    private function salesByPaymentDestination(int $businessId, mixed $monthStart, mixed $monthEnd, ?int $branchId = null): array
    {
        $totals = Sale::query()
            ->forBusiness($businessId)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
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
