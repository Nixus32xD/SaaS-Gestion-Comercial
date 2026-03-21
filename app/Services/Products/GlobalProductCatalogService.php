<?php

namespace App\Services\Products;

use App\Models\Category;
use App\Models\GlobalProduct;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class GlobalProductCatalogService
{
    public function __construct(private readonly ProductNameNormalizer $nameNormalizer) {}

    /**
     * @return array<string, mixed>
     */
    public function lookupForBusiness(int $businessId, ?string $barcode, ?string $sku): array
    {
        $barcode = $this->sanitizeIdentity($barcode);
        $sku = $this->sanitizeIdentity($sku);
        $localProduct = $this->findLocalProduct($businessId, $barcode, $sku);

        if ($localProduct !== null) {
            return [
                'searched_by' => $barcode !== null ? 'barcode' : 'sku',
                'local_product' => $this->mapLocalProduct($localProduct),
                'global_product' => null,
                'conflict' => null,
            ];
        }

        $match = $this->findMatchingGlobalProduct($barcode, $sku);

        return [
            'searched_by' => $barcode !== null ? 'barcode' : ($sku !== null ? 'sku' : null),
            'local_product' => null,
            'global_product' => $match['product'] instanceof GlobalProduct
                ? $this->mapGlobalProduct($match['product'], $businessId)
                : null,
            'conflict' => $match['conflict'] ? $match['message'] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function syncLocalProduct(Product $product): array
    {
        return DB::transaction(function () use ($product): array {
            /** @var Product $localProduct */
            $localProduct = Product::query()
                ->with(['category:id,name'])
                ->lockForUpdate()
                ->findOrFail($product->id);

            if (! $this->hasCatalogIdentifier($localProduct->barcode, $localProduct->sku)) {
                return [
                    'status' => 'skipped',
                    'linked' => false,
                    'message' => 'Producto omitido por no tener barcode ni SKU.',
                ];
            }

            if ($localProduct->global_product_id !== null) {
                $globalProduct = GlobalProduct::query()->find($localProduct->global_product_id);

                if ($globalProduct !== null) {
                    $this->fillMissingGlobalFields($globalProduct, $localProduct);

                    return [
                        'status' => 'existing',
                        'linked' => false,
                        'message' => null,
                    ];
                }
            }

            $match = $this->findMatchingGlobalProduct($localProduct->barcode, $localProduct->sku);

            if ($match['conflict']) {
                return [
                    'status' => 'conflict',
                    'linked' => false,
                    'message' => $match['message'],
                ];
            }

            /** @var GlobalProduct|null $globalProduct */
            $globalProduct = $match['product'];
            $created = false;

            if ($globalProduct === null) {
                $globalProduct = $this->createGlobalProductFromLocal($localProduct);
                $created = true;
            } else {
                $this->fillMissingGlobalFields($globalProduct, $localProduct);
            }

            $localProduct->global_product_id = $globalProduct->id;
            $localProduct->save();

            return [
                'status' => $created ? 'created' : 'existing',
                'linked' => true,
                'message' => null,
            ];
        });
    }

    public function resolveCategoryIdForBusiness(int $businessId, ?GlobalProduct $globalProduct): ?int
    {
        if ($globalProduct === null) {
            return null;
        }

        $globalProduct->loadMissing('category:id,name');
        $categoryName = trim((string) $globalProduct->category?->name);

        if ($categoryName === '') {
            return null;
        }

        $normalizedCategoryName = $this->nameNormalizer->normalize($categoryName);

        return Category::query()
            ->forBusiness($businessId)
            ->where('is_active', true)
            ->get(['id', 'name'])
            ->first(fn (Category $category) => $this->nameNormalizer->normalize($category->name) === $normalizedCategoryName)
            ?->id;
    }

    /**
     * @return array{product: ?GlobalProduct, conflict: bool, message: ?string}
     */
    private function findMatchingGlobalProduct(?string $barcode, ?string $sku): array
    {
        $barcode = $this->sanitizeIdentity($barcode);
        $sku = $this->sanitizeIdentity($sku);

        if (! $this->hasCatalogIdentifier($barcode, $sku)) {
            return [
                'product' => null,
                'conflict' => false,
                'message' => null,
            ];
        }

        $byBarcode = $barcode === null
            ? null
            : GlobalProduct::query()
                ->with('category:id,name')
                ->where('barcode', $barcode)
                ->first();

        $bySku = $sku === null
            ? null
            : GlobalProduct::query()
                ->with('category:id,name')
                ->where('sku', $sku)
                ->first();

        if ($byBarcode !== null && $bySku !== null && $byBarcode->id !== $bySku->id) {
            return [
                'product' => null,
                'conflict' => true,
                'message' => 'Conflicto de identificadores: el barcode y el SKU apuntan a productos globales distintos.',
            ];
        }

        if ($byBarcode !== null) {
            if ($sku !== null && $byBarcode->sku !== null && $byBarcode->sku !== $sku) {
                return [
                    'product' => null,
                    'conflict' => true,
                    'message' => 'Conflicto de SKU: el producto global encontrado por barcode ya tiene otro SKU asignado.',
                ];
            }

            return [
                'product' => $byBarcode,
                'conflict' => false,
                'message' => null,
            ];
        }

        if ($bySku !== null) {
            if ($barcode !== null && $bySku->barcode !== null && $bySku->barcode !== $barcode) {
                return [
                    'product' => null,
                    'conflict' => true,
                    'message' => 'Conflicto de barcode: el producto global encontrado por SKU ya tiene otro barcode asignado.',
                ];
            }

            return [
                'product' => $bySku,
                'conflict' => false,
                'message' => null,
            ];
        }

        return [
            'product' => null,
            'conflict' => false,
            'message' => null,
        ];
    }

    private function createGlobalProductFromLocal(Product $product): GlobalProduct
    {
        $attributes = [
            'barcode' => $this->sanitizeIdentity($product->barcode),
            'sku' => $this->sanitizeIdentity($product->sku),
            'name' => $product->name,
            'category_id' => $product->category_id,
            'normalized_name' => $this->nameNormalizer->normalize($product->name),
        ];

        try {
            return GlobalProduct::query()->create($attributes);
        } catch (QueryException $exception) {
            $match = $this->findMatchingGlobalProduct($product->barcode, $product->sku);

            if ($match['product'] instanceof GlobalProduct) {
                $this->fillMissingGlobalFields($match['product'], $product);

                return $match['product'];
            }

            throw $exception;
        }
    }

    private function fillMissingGlobalFields(GlobalProduct $globalProduct, Product $localProduct): void
    {
        $dirty = false;
        $barcode = $this->sanitizeIdentity($localProduct->barcode);
        $sku = $this->sanitizeIdentity($localProduct->sku);

        if ($globalProduct->barcode === null && $barcode !== null) {
            $globalProduct->barcode = $barcode;
            $dirty = true;
        }

        if ($globalProduct->sku === null && $sku !== null) {
            $globalProduct->sku = $sku;
            $dirty = true;
        }

        if ($globalProduct->category_id === null && $localProduct->category_id !== null) {
            $globalProduct->category_id = $localProduct->category_id;
            $dirty = true;
        }

        if ($dirty) {
            $globalProduct->save();
        }
    }

    private function findLocalProduct(int $businessId, ?string $barcode, ?string $sku): ?Product
    {
        if ($barcode !== null) {
            $localByBarcode = Product::query()
                ->forBusiness($businessId)
                ->with('category:id,name')
                ->where('barcode', $barcode)
                ->first();

            if ($localByBarcode !== null) {
                return $localByBarcode;
            }
        }

        if ($sku === null) {
            return null;
        }

        return Product::query()
            ->forBusiness($businessId)
            ->with('category:id,name')
            ->where('sku', $sku)
            ->first();
    }

    private function hasCatalogIdentifier(?string $barcode, ?string $sku): bool
    {
        return $this->sanitizeIdentity($barcode) !== null || $this->sanitizeIdentity($sku) !== null;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapLocalProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'barcode' => $product->barcode,
            'sku' => $product->sku,
            'category' => $product->category?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapGlobalProduct(GlobalProduct $product, int $businessId): array
    {
        $suggestedCategoryId = $this->resolveCategoryIdForBusiness($businessId, $product);
        $suggestedCategory = $suggestedCategoryId === null
            ? null
            : Category::query()->find($suggestedCategoryId, ['id', 'name']);

        return [
            'id' => $product->id,
            'name' => $product->name,
            'barcode' => $product->barcode,
            'sku' => $product->sku,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
            ] : null,
            'suggested_category' => $suggestedCategory ? [
                'id' => $suggestedCategory->id,
                'name' => $suggestedCategory->name,
            ] : null,
        ];
    }

    private function sanitizeIdentity(?string $value): ?string
    {
        $identity = trim((string) $value);

        return $identity === '' ? null : $identity;
    }
}
