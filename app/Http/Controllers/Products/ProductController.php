<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Controller;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Support\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request, CurrentBusiness $currentBusiness): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        $search = trim((string) $request->query('search', ''));

        $products = Product::query()
            ->forBusiness($business->id)
            ->with('supplier')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'barcode' => $product->barcode,
                'sku' => $product->sku,
                'unit_type' => $product->unit_type,
                'sale_price' => (float) $product->sale_price,
                'cost_price' => (float) $product->cost_price,
                'stock' => (float) $product->stock,
                'min_stock' => (float) $product->min_stock,
                'shelf_life_days' => $product->shelf_life_days,
                'expiry_alert_days' => $product->expiry_alert_days,
                'is_active' => $product->is_active,
                'supplier' => $product->supplier?->name,
                'has_low_stock' => (float) $product->stock <= (float) $product->min_stock,
            ]);

        return Inertia::render('Products/Index', [
            'filters' => [
                'search' => $search,
            ],
            'products' => $products,
        ]);
    }

    public function create(CurrentBusiness $currentBusiness): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        return Inertia::render('Products/Create', [
            'suppliers' => Supplier::query()
                ->forBusiness($business->id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(
        StoreProductRequest $request,
        CurrentBusiness $currentBusiness
    ): RedirectResponse {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        $data = $request->validated();
        $supplierId = $this->resolveSupplierId($business->id, $data['supplier_id'] ?? null);
        $initialStock = round((float) ($data['stock'] ?? 0), 3);

        $product = Product::query()->create([
            'business_id' => $business->id,
            'supplier_id' => $supplierId,
            'name' => $data['name'],
            'slug' => $this->buildUniqueSlug($business->id, $data['slug'] ?: $data['name']),
            'description' => $data['description'] ?: null,
            'barcode' => $data['barcode'] ?: null,
            'sku' => $data['sku'] ?: null,
            'unit_type' => $data['unit_type'],
            'sale_price' => $data['sale_price'],
            'cost_price' => $data['cost_price'],
            'stock' => $initialStock,
            'min_stock' => $data['min_stock'] ?? 0,
            'shelf_life_days' => ($data['shelf_life_days'] ?? null) !== null ? (int) $data['shelf_life_days'] : null,
            'expiry_alert_days' => (int) ($data['expiry_alert_days'] ?? 15),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        if ($initialStock > 0) {
            StockMovement::query()->create([
                'business_id' => $business->id,
                'product_id' => $product->id,
                'type' => 'initial',
                'reference_type' => Product::class,
                'reference_id' => $product->id,
                'quantity' => $initialStock,
                'stock_before' => 0,
                'stock_after' => $initialStock,
                'notes' => 'Stock inicial del producto',
                'created_by' => $request->user()?->id,
            ]);
        }

        return redirect()
            ->route('products.index')
            ->with('success', 'Producto creado correctamente.');
    }

    public function edit(CurrentBusiness $currentBusiness, Product $product): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);
        abort_if($product->business_id !== $business->id, 403);

        return Inertia::render('Products/Edit', [
            'product' => [
                'id' => $product->id,
                'supplier_id' => $product->supplier_id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'barcode' => $product->barcode,
                'sku' => $product->sku,
                'unit_type' => $product->unit_type,
                'sale_price' => (float) $product->sale_price,
                'cost_price' => (float) $product->cost_price,
                'stock' => (float) $product->stock,
                'min_stock' => (float) $product->min_stock,
                'shelf_life_days' => $product->shelf_life_days,
                'expiry_alert_days' => $product->expiry_alert_days,
                'is_active' => $product->is_active,
            ],
            'suppliers' => Supplier::query()
                ->forBusiness($business->id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function update(
        UpdateProductRequest $request,
        CurrentBusiness $currentBusiness,
        Product $product
    ): RedirectResponse {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);
        abort_if($product->business_id !== $business->id, 403);

        $data = $request->validated();
        $supplierId = $this->resolveSupplierId($business->id, $data['supplier_id'] ?? null);

        $beforeStock = round((float) $product->stock, 3);
        $newStock = round((float) ($data['stock'] ?? $beforeStock), 3);

        $product->update([
            'supplier_id' => $supplierId,
            'name' => $data['name'],
            'slug' => $this->buildUniqueSlug($business->id, $data['slug'] ?: $data['name'], $product->id),
            'description' => $data['description'] ?: null,
            'barcode' => $data['barcode'] ?: null,
            'sku' => $data['sku'] ?: null,
            'unit_type' => $data['unit_type'],
            'sale_price' => $data['sale_price'],
            'cost_price' => $data['cost_price'],
            'stock' => $newStock,
            'min_stock' => $data['min_stock'] ?? 0,
            'shelf_life_days' => ($data['shelf_life_days'] ?? null) !== null ? (int) $data['shelf_life_days'] : null,
            'expiry_alert_days' => (int) ($data['expiry_alert_days'] ?? 15),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        if ($beforeStock !== $newStock) {
            StockMovement::query()->create([
                'business_id' => $business->id,
                'product_id' => $product->id,
                'type' => 'adjustment',
                'reference_type' => Product::class,
                'reference_id' => $product->id,
                'quantity' => round($newStock - $beforeStock, 3),
                'stock_before' => $beforeStock,
                'stock_after' => $newStock,
                'notes' => 'Ajuste manual desde edicion de producto',
                'created_by' => $request->user()?->id,
            ]);
        }

        return redirect()
            ->route('products.index')
            ->with('success', 'Producto actualizado correctamente.');
    }

    private function resolveSupplierId(int $businessId, mixed $supplierId): ?int
    {
        if ($supplierId === null || $supplierId === '') {
            return null;
        }

        $supplier = Supplier::query()
            ->forBusiness($businessId)
            ->whereKey((int) $supplierId)
            ->first();

        if ($supplier === null) {
            throw ValidationException::withMessages([
                'supplier_id' => 'El proveedor seleccionado no pertenece al comercio.',
            ]);
        }

        return $supplier->id;
    }

    private function buildUniqueSlug(int $businessId, string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value);
        $root = $base === '' ? 'product' : $base;
        $slug = $root;
        $counter = 1;

        while ($this->slugExists($businessId, $slug, $ignoreId)) {
            $slug = $root.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function slugExists(int $businessId, string $slug, ?int $ignoreId = null): bool
    {
        return Product::query()
            ->forBusiness($businessId)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists();
    }
}
