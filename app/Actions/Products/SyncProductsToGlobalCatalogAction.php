<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Services\Products\GlobalProductCatalogService;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncProductsToGlobalCatalogAction
{
    public function __construct(private readonly GlobalProductCatalogService $catalogService) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $summary = [
            'analyzed' => 0,
            'created' => 0,
            'existing' => 0,
            'linked' => 0,
            'conflicts' => 0,
            'error_count' => 0,
            'errors' => [],
        ];

        Product::query()
            ->select([
                'id',
                'business_id',
                'global_product_id',
                'category_id',
                'name',
                'barcode',
            ])
            ->orderBy('id')
            ->chunkById(200, function ($products) use (&$summary): void {
                foreach ($products as $product) {
                    $summary['analyzed']++;

                    try {
                        $result = $this->catalogService->syncLocalProduct($product);

                        if ($result['status'] === 'created') {
                            $summary['created']++;
                        }

                        if ($result['status'] === 'existing') {
                            $summary['existing']++;
                        }

                        if ($result['status'] === 'conflict') {
                            $summary['conflicts']++;
                            $summary['errors'][] = $result['message'];
                        }

                        if ($result['linked']) {
                            $summary['linked']++;
                        }
                    } catch (Throwable $exception) {
                        $summary['error_count']++;
                        $summary['errors'][] = "Producto #{$product->id}: {$exception->getMessage()}";

                        Log::error('No se pudo sincronizar un producto al catalogo global.', [
                            'product_id' => $product->id,
                            'business_id' => $product->business_id,
                            'exception' => $exception,
                        ]);
                    }
                }
            });

        $summary['errors'] = array_values(array_slice($summary['errors'], 0, 20));

        return $summary;
    }
}
