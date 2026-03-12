<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
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

        $todaySales = (float) Sale::query()
            ->forBusiness($business->id)
            ->whereDate('sold_at', now()->toDateString())
            ->sum('total');

        $monthSales = (float) Sale::query()
            ->forBusiness($business->id)
            ->whereBetween('sold_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('total');

        $productsCount = Product::query()->forBusiness($business->id)->count();
        $suppliersCount = Supplier::query()->forBusiness($business->id)->count();

        $lowStock = Product::query()
            ->forBusiness($business->id)
            ->whereColumn('stock', '<=', 'min_stock')
            ->orderBy('stock')
            ->limit(8)
            ->get();

        $topProducts = SaleItem::query()
            ->select([
                'product_id',
                'product_name',
                DB::raw('SUM(quantity) as sold_quantity'),
            ])
            ->forBusiness($business->id)
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('sold_quantity')
            ->limit(8)
            ->get();

        $latestSales = Sale::query()
            ->forBusiness($business->id)
            ->with('user')
            ->latest('sold_at')
            ->limit(8)
            ->get();

        $latestPurchases = Purchase::query()
            ->forBusiness($business->id)
            ->with('supplier')
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
                'sold_quantity' => (float) $row->sold_quantity,
            ]),
            'latest_sales' => $latestSales->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'total' => (float) $sale->total,
                'sold_at' => $sale->sold_at?->format('Y-m-d H:i'),
                'user' => $sale->user?->name,
            ]),
            'latest_purchases' => $latestPurchases->map(fn (Purchase $purchase) => [
                'id' => $purchase->id,
                'purchase_number' => $purchase->purchase_number,
                'total' => (float) $purchase->total,
                'purchased_at' => $purchase->purchased_at?->format('Y-m-d H:i'),
                'supplier' => $purchase->supplier?->name,
            ]),
            'expiration_alerts' => $expirationAlerts->all(),
        ]);
    }
}
