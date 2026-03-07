<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Support\CurrentBusiness;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(CurrentBusiness $currentBusiness): Response
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
                'sale_items.product_id',
                'sale_items.product_name',
                DB::raw('SUM(sale_items.quantity) as sold_quantity'),
            ])
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.business_id', $business->id)
            ->groupBy('sale_items.product_id', 'sale_items.product_name')
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

        return Inertia::render('Dashboard/Index', [
            'summary' => [
                'today_sales' => $todaySales,
                'month_sales' => $monthSales,
                'products_count' => $productsCount,
                'suppliers_count' => $suppliersCount,
            ],
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
        ]);
    }
}
