<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Controller;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Models\Category;
use App\Models\GlobalProduct;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\ProductBatchService;
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
        $filters = [
            'search' => $search,
            'category_id' => $categoryId,
            'no_price' => $request->boolean('no_price'),
            'no_cost' => $request->boolean('no_cost'),
            'no_stock' => $request->boolean('no_stock'),
            'with_stock' => $request->boolean('with_stock'),
            'low_stock' => $request->boolean('low_stock'),
        ];

        $products = Product::query()
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
            ->filter($filters)
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
            ]);

        return Inertia::render('Products/Index', [
            'filters' => $filters,
            'categories' => fn () => Category::query()
                ->forBusiness($business->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'products' => $products,
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
        GlobalProductCatalogService $catalogService,
        ProductBatchService $productBatchService
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
            $globalProduct,
            $productBatchService
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

                $productBatchService->receiveStock($business, $product, $initialStock, [
                    'batch_code' => $data['batch_code'] ?? null,
                    'expires_at' => $data['batch_expires_at'] ?? null,
                    'unit_cost' => $data['cost_price'],
                    'movement_type' => 'initial',
                    'reference_type' => Product::class,
                    'reference_id' => $product->id,
                    'notes' => 'Stock inicial del producto',
                    'created_by' => $request->user()?->id,
                    'error_key' => 'batch_code',
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

        $product->load([
            'batches' => fn ($query) => $query
                ->available()
                ->orderedForOutbound(),
        ]);

        $batchSummary = $this->buildBatchSummary($product);

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
                'batch_summary' => $batchSummary,
                'batches' => $product->batches
                    ->map(fn (ProductBatch $batch) => $this->mapBatchForDisplay($batch, (int) ($product->expiry_alert_days ?? 15)))
                    ->values()
                    ->all(),
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
        Product $product,
        ProductBatchService $productBatchService
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
            $request,
            $productBatchService
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
                $stockDelta = round($newStock - $beforeStock, 3);

                if ($stockDelta > 0) {
                    $productBatchService->receiveStock($business, $product, $stockDelta, [
                        'batch_code' => $data['batch_code'] ?? null,
                        'expires_at' => $data['batch_expires_at'] ?? null,
                        'unit_cost' => $data['cost_price'],
                        'movement_type' => 'adjustment',
                        'reference_type' => Product::class,
                        'reference_id' => $product->id,
                        'notes' => 'Ajuste manual desde edicion de producto',
                        'created_by' => $request->user()?->id,
                        'error_key' => 'batch_code',
                    ]);
                } elseif ($stockDelta < 0) {
                    $productBatchService->consumeStock($business, $product, abs($stockDelta), [
                        'movement_type' => 'adjustment',
                        'reference_type' => Product::class,
                        'reference_id' => $product->id,
                        'notes' => 'Ajuste manual desde edicion de producto',
                        'created_by' => $request->user()?->id,
                    ]);
                }

                StockMovement::query()->create([
                    'business_id' => $business->id,
                    'product_id' => $product->id,
                    'type' => 'adjustment',
                    'reference_type' => Product::class,
                    'reference_id' => $product->id,
                    'quantity' => $stockDelta,
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

    /**
     * @return array<string, mixed>
     */
    private function mapBatchForDisplay(ProductBatch $batch, int $alertDays): array
    {
        $status = $batch->expirationStatus($alertDays);

        return [
            'id' => $batch->id,
            'batch_code' => $batch->batch_code,
            'quantity' => (float) $batch->quantity,
            'unit_cost' => $batch->unit_cost !== null ? (float) $batch->unit_cost : null,
            'expires_at' => $batch->expires_at?->toDateString(),
            'status' => $status,
            'status_label' => match ($status) {
                'expired' => 'Vencido',
                'upcoming' => 'Proximo',
                'no_expiration' => 'Sin vencimiento',
                default => 'Vigente',
            },
        ];
    }

    /**
     * @return array<string, float|int|bool>
     */
    private function buildBatchSummary(Product $product): array
    {
        $trackedStock = round((float) $product->batches->sum('quantity'), 3);
        $totalStock = round((float) $product->stock, 3);

        return [
            'tracked_stock' => $trackedStock,
            'untracked_stock' => max(0, round($totalStock - $trackedStock, 3)),
            'total_stock' => $totalStock,
            'has_batches' => $product->batches->isNotEmpty(),
            'batches_count' => $product->batches->count(),
        ];
    }
}
