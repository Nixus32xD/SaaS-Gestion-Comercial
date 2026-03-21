<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Controller;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Models\Category;
use App\Models\GlobalProduct;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\Products\GlobalProductCatalogService;
use App\Support\CurrentBusiness;
use App\Support\ProductMeasurement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $categoryId = $this->resolveCategoryFilter($business->id, $request->query('category_id'));

        return Inertia::render('Products/Index', [
            'filters' => [
                'search' => $search,
                'category_id' => $categoryId,
            ],
            'categories' => fn () => Category::query()
                ->forBusiness($business->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'products' => fn () => Product::query()
                ->forBusiness($business->id)
                ->select([
                    'id',
                    'business_id',
                    'category_id',
                    'supplier_id',
                    'name',
                    'barcode',
                    'sku',
                    'unit_type',
                    'weight_unit',
                    'sale_price',
                    'cost_price',
                    'stock',
                    'min_stock',
                    'shelf_life_days',
                    'expiry_alert_days',
                    'is_active',
                ])
                ->with([
                    'supplier:id,name',
                    'category:id,name',
                ])
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($innerQuery) use ($search): void {
                        $innerQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('barcode', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
                })
                ->when($categoryId !== null, fn ($query) => $query->where('category_id', $categoryId))
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'barcode' => $product->barcode,
                    'sku' => $product->sku,
                    'unit_type' => $product->unit_type,
                    'weight_unit' => $product->weight_unit,
                    'type_label' => ProductMeasurement::typeLabel($product->unit_type, $product->weight_unit),
                    'quantity_label' => ProductMeasurement::quantityLabel($product->unit_type, $product->weight_unit),
                    'price_label' => ProductMeasurement::priceLabel($product->unit_type, $product->weight_unit),
                    'sale_price' => (float) $product->sale_price,
                    'cost_price' => (float) $product->cost_price,
                    'stock' => (float) $product->stock,
                    'min_stock' => (float) $product->min_stock,
                    'shelf_life_days' => $product->shelf_life_days,
                    'expiry_alert_days' => $product->expiry_alert_days,
                    'is_active' => $product->is_active,
                    'category' => $product->category?->name,
                    'supplier' => $product->supplier?->name,
                    'has_low_stock' => (float) $product->stock <= (float) $product->min_stock,
                ]),
        ]);
    }

    public function create(CurrentBusiness $currentBusiness): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        return Inertia::render('Products/Create', [
            'categories' => Category::query()
                ->forBusiness($business->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'suppliers' => Supplier::query()
                ->forBusiness($business->id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'global_catalog' => [
                'enabled' => $business->hasGlobalProductCatalog(),
            ],
        ]);
    }

    public function lookupCatalog(
        Request $request,
        CurrentBusiness $currentBusiness,
        GlobalProductCatalogService $catalogService
    ): JsonResponse {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);
        abort_unless($business->hasGlobalProductCatalog(), 403, 'Catalogo global no habilitado para este comercio.');

        return response()->json(
            $catalogService->lookupForBusiness(
                $business->id,
                $request->string('barcode')->trim()->value(),
                $request->string('sku')->trim()->value(),
            )
        );
    }

    public function store(
        StoreProductRequest $request,
        CurrentBusiness $currentBusiness,
        GlobalProductCatalogService $catalogService
    ): RedirectResponse {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        $data = $request->validated();
        $globalCatalogEnabled = $business->hasGlobalProductCatalog();
        $this->ensureGlobalCatalogEnabledForStore($globalCatalogEnabled, $data['global_product_id'] ?? null);
        $globalProduct = $this->resolveGlobalProduct($data['global_product_id'] ?? null);
        $categoryId = $this->resolveCategoryId($business->id, $data['category_id'] ?? null);
        if ($globalCatalogEnabled) {
            $categoryId ??= $catalogService->resolveCategoryIdForBusiness($business->id, $globalProduct);
        }
        $supplierId = $this->resolveSupplierId($business->id, $data['supplier_id'] ?? null);
        $initialStock = round((float) ($data['stock'] ?? 0), 3);

        DB::transaction(function () use (
            $business,
            $categoryId,
            $supplierId,
            $data,
            $initialStock,
            $request,
            $globalProduct
        ): void {
            $product = Product::query()->create([
                'business_id' => $business->id,
                'global_product_id' => $globalProduct?->id,
                'category_id' => $categoryId,
                'supplier_id' => $supplierId,
                'name' => $data['name'],
                'slug' => $this->buildUniqueSlug($business->id, $data['slug'] ?: $data['name']),
                'description' => $data['description'] ?: null,
                'barcode' => $data['barcode'] ?: null,
                'sku' => $data['sku'] ?: null,
                'unit_type' => $data['unit_type'],
                'weight_unit' => ProductMeasurement::normalizeWeightUnit($data['unit_type'], $data['weight_unit'] ?? null),
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
        });

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
                'category_id' => $product->category_id,
                'supplier_id' => $product->supplier_id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'barcode' => $product->barcode,
                'sku' => $product->sku,
                'unit_type' => $product->unit_type,
                'weight_unit' => $product->weight_unit,
                'sale_price' => (float) $product->sale_price,
                'cost_price' => (float) $product->cost_price,
                'stock' => (float) $product->stock,
                'min_stock' => (float) $product->min_stock,
                'shelf_life_days' => $product->shelf_life_days,
                'expiry_alert_days' => $product->expiry_alert_days,
                'is_active' => $product->is_active,
            ],
            'categories' => Category::query()
                ->forBusiness($business->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
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
        $categoryId = $this->resolveCategoryId($business->id, $data['category_id'] ?? null);
        $supplierId = $this->resolveSupplierId($business->id, $data['supplier_id'] ?? null);

        $beforeStock = round((float) $product->stock, 3);
        $newStock = round((float) ($data['stock'] ?? $beforeStock), 3);

        DB::transaction(function () use (
            $product,
            $categoryId,
            $supplierId,
            $data,
            $business,
            $newStock,
            $beforeStock,
            $request
        ): void {
            $product->update([
                'category_id' => $categoryId,
                'supplier_id' => $supplierId,
                'name' => $data['name'],
                'slug' => $this->buildUniqueSlug($business->id, $data['slug'] ?: $data['name'], $product->id),
                'description' => $data['description'] ?: null,
                'barcode' => $data['barcode'] ?: null,
                'sku' => $data['sku'] ?: null,
                'unit_type' => $data['unit_type'],
                'weight_unit' => ProductMeasurement::normalizeWeightUnit($data['unit_type'], $data['weight_unit'] ?? null),
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
        });

        return redirect()
            ->route('products.index')
            ->with('success', 'Producto actualizado correctamente.');
    }

    private function resolveGlobalProduct(mixed $globalProductId): ?GlobalProduct
    {
        if ($globalProductId === null || $globalProductId === '') {
            return null;
        }

        return GlobalProduct::query()->find((int) $globalProductId);
    }

    private function ensureGlobalCatalogEnabledForStore(bool $enabled, mixed $globalProductId): void
    {
        if ($enabled || $globalProductId === null || $globalProductId === '') {
            return;
        }

        throw ValidationException::withMessages([
            'global_product_id' => 'El catalogo global no esta habilitado para este comercio.',
        ]);
    }

    private function resolveCategoryFilter(int $businessId, mixed $categoryId): ?int
    {
        if ($categoryId === null || $categoryId === '') {
            return null;
        }

        $category = Category::query()
            ->forBusiness($businessId)
            ->whereKey((int) $categoryId)
            ->first();

        return $category?->id;
    }

    private function resolveCategoryId(int $businessId, mixed $categoryId): ?int
    {
        if ($categoryId === null || $categoryId === '') {
            return null;
        }

        $category = Category::query()
            ->forBusiness($businessId)
            ->whereKey((int) $categoryId)
            ->first();

        if ($category === null) {
            throw ValidationException::withMessages([
                'category_id' => 'La categoria seleccionada no pertenece al comercio.',
            ]);
        }

        return $category->id;
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
