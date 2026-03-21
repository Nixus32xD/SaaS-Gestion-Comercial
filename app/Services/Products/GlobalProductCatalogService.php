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
    public function lookupForBusiness(int $businessId, ?string $barcode, ?string $name): array
    {
        $barcode = $this->sanitizeBarcode($barcode);
        $name = $this->sanitizeName($name);

        $localProduct = $barcode === null
            ? null
            : Product::query()
                ->forBusiness($businessId)
                ->with('category:id,name')
                ->where('barcode', $barcode)
                ->first();

        if ($localProduct !== null) {
            return [
                'searched_by' => 'barcode',
                'local_product' => $this->mapLocalProduct($localProduct),
                'global_product' => null,
            ];
        }

        $match = $this->findMatchingGlobalProduct($barcode, $name);

        return [
            'searched_by' => $barcode !== null ? 'barcode' : 'name',
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

            $match = $this->findMatchingGlobalProduct($localProduct->barcode, $localProduct->name);

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
    private function findMatchingGlobalProduct(?string $barcode, ?string $name): array
    {
        $barcode = $this->sanitizeBarcode($barcode);
        $name = $this->sanitizeName($name);

        if ($barcode !== null) {
            $byBarcode = GlobalProduct::query()
                ->with('category:id,name')
                ->where('barcode', $barcode)
                ->first();

            if ($byBarcode !== null) {
                return [
                    'product' => $byBarcode,
                    'conflict' => false,
                    'message' => null,
                ];
            }
        }

        if ($name === null) {
            return [
                'product' => null,
                'conflict' => false,
                'message' => null,
            ];
        }

        $normalizedName = $this->nameNormalizer->normalize($name);

        if ($normalizedName === '') {
            return [
                'product' => null,
                'conflict' => false,
                'message' => null,
            ];
        }

        $byName = GlobalProduct::query()
            ->with('category:id,name')
            ->where('normalized_name', $normalizedName)
            ->first();

        if ($byName === null) {
            return [
                'product' => null,
                'conflict' => false,
                'message' => null,
            ];
        }

        if ($barcode !== null && $byName->barcode !== null && $byName->barcode !== $barcode) {
            return [
                'product' => null,
                'conflict' => true,
                'message' => "Conflicto para \"{$name}\": el catalogo global ya tiene otro codigo de barras asignado.",
            ];
        }

        return [
            'product' => $byName,
            'conflict' => false,
            'message' => null,
        ];
    }

    private function createGlobalProductFromLocal(Product $product): GlobalProduct
    {
        $attributes = [
            'barcode' => $this->sanitizeBarcode($product->barcode),
            'name' => $product->name,
            'category_id' => $product->category_id,
            'normalized_name' => $this->nameNormalizer->normalize($product->name),
        ];

        try {
            return GlobalProduct::query()->create($attributes);
        } catch (QueryException $exception) {
            $match = $this->findMatchingGlobalProduct($product->barcode, $product->name);

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
        $barcode = $this->sanitizeBarcode($localProduct->barcode);

        if ($globalProduct->barcode === null && $barcode !== null) {
            $globalProduct->barcode = $barcode;
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

    /**
     * @return array<string, mixed>
     */
    private function mapLocalProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'barcode' => $product->barcode,
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

    private function sanitizeBarcode(?string $value): ?string
    {
        $barcode = trim((string) $value);

        return $barcode === '' ? null : $barcode;
    }

    private function sanitizeName(?string $value): ?string
    {
        $name = trim((string) $value);

        return $name === '' ? null : $name;
    }
}
