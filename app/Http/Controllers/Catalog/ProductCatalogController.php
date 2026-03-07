<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\BranchStock;
use App\Domain\Tenancy\Support\CurrentTenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreProductRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProductCatalogController extends Controller
{
    public function index(CurrentTenant $currentTenant): Response
    {
        $tenant = $currentTenant->tenant();
        $branch = $currentTenant->branch();
        abort_if($tenant === null || $branch === null, 404);

        $products = Product::query()
            ->where('tenant_id', $tenant->id)
            ->with([
                'branchStocks' => fn ($query) => $query
                    ->where('tenant_id', $tenant->id)
                    ->where('branch_id', $branch->id),
            ])
            ->orderByDesc('id')
            ->limit(150)
            ->get();

        return Inertia::render('Catalog/Products', [
            'units' => ['unidad', 'kg', 'litro', 'metro'],
            'products' => $products->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'unit' => $product->unit,
                'base_price' => (float) $product->base_price,
                'stock' => (float) ($product->branchStocks->first()?->stock ?? 0),
                'minimum' => (float) ($product->branchStocks->first()?->minimum ?? 0),
                'status' => $product->status,
            ]),
        ]);
    }

    public function store(StoreProductRequest $request, CurrentTenant $currentTenant): RedirectResponse
    {
        $tenant = $currentTenant->tenant();
        $branch = $currentTenant->branch();
        abort_if($tenant === null || $branch === null, 404);

        $validated = $request->validated();
        $sku = trim((string) ($validated['sku'] ?? ''));

        if ($sku !== '') {
            $skuExists = Product::query()
                ->where('tenant_id', $tenant->id)
                ->where('sku', $sku)
                ->exists();

            if ($skuExists) {
                throw ValidationException::withMessages([
                    'sku' => 'Ya existe un producto con ese SKU en este comercio.',
                ]);
            }
        }

        DB::transaction(function () use ($validated, $tenant, $branch, $sku): void {
            $product = Product::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $validated['name'],
                'sku' => $sku !== '' ? $sku : null,
                'unit' => $validated['unit'],
                'base_price' => $validated['base_price'],
                'status' => 'active',
            ]);

            BranchStock::query()->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'product_id' => $product->id,
                'stock' => 0,
                'reserved' => 0,
                'minimum' => $validated['minimum'] ?? 0,
            ]);
        });

        return back()->with('success', 'Producto creado correctamente.');
    }
}
