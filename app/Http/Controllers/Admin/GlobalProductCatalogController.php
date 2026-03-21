<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Products\SyncProductsToGlobalCatalogAction;
use App\Http\Controllers\Controller;
use App\Models\GlobalProduct;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GlobalProductCatalogController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/GlobalProducts/Index', [
            'stats' => [
                'total' => GlobalProduct::query()->count(),
                'with_identifier' => GlobalProduct::query()
                    ->where(function ($query): void {
                        $query->whereNotNull('barcode')->orWhereNotNull('sku');
                    })
                    ->count(),
                'without_category' => GlobalProduct::query()->whereNull('category_id')->count(),
                'linked_local_products' => Product::query()->whereNotNull('global_product_id')->count(),
            ],
            'recent_global_products' => GlobalProduct::query()
                ->with('category:id,name')
                ->latest('id')
                ->limit(8)
                ->get()
                ->map(fn (GlobalProduct $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'barcode' => $product->barcode,
                    'sku' => $product->sku,
                    'category' => $product->category?->name,
                    'updated_at' => $product->updated_at?->format('Y-m-d H:i'),
                ])
                ->values()
                ->all(),
            'last_sync_summary' => $request->session()->get('global_catalog_sync_summary'),
        ]);
    }

    public function sync(SyncProductsToGlobalCatalogAction $action): RedirectResponse
    {
        $summary = $action->execute();

        return redirect()
            ->route('admin.global-products.index')
            ->with('success', 'Sincronizacion al catalogo global completada.')
            ->with('global_catalog_sync_summary', $summary);
    }
}
