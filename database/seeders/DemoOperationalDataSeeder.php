<?php

namespace Database\Seeders;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\BranchStock;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Database\Seeder;

class DemoOperationalDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenant = Tenant::query()->first();
        if ($tenant === null) {
            return;
        }

        $branch = $tenant->branches()->where('is_main', true)->first() ?? $tenant->branches()->first();
        if ($branch === null) {
            return;
        }

        $products = [
            ['name' => 'Gaseosa Cola 2.25L', 'sku' => '779123456001', 'unit' => 'unidad', 'base_price' => 2500, 'status' => 'active'],
            ['name' => 'Papas Clasicas 150g', 'sku' => '779123456002', 'unit' => 'unidad', 'base_price' => 1800, 'status' => 'active'],
            ['name' => 'Yerba 1kg', 'sku' => '779123456003', 'unit' => 'unidad', 'base_price' => 4200, 'status' => 'active'],
            ['name' => 'Queso Cremoso x kg', 'sku' => '779123456004', 'unit' => 'kg', 'base_price' => 8900, 'status' => 'active'],
            ['name' => 'Detergente 500ml', 'sku' => '779123456005', 'unit' => 'unidad', 'base_price' => 1600, 'status' => 'active'],
            ['name' => 'Pan rallado 1kg', 'sku' => '779123456006', 'unit' => 'unidad', 'base_price' => 1400, 'status' => 'active'],
        ];

        $productPayload = collect($products)->map(fn (array $product) => [
            'tenant_id' => $tenant->id,
            'name' => $product['name'],
            'sku' => $product['sku'],
            'unit' => $product['unit'],
            'base_price' => $product['base_price'],
            'status' => $product['status'],
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        Product::query()->upsert(
            $productPayload,
            ['tenant_id', 'sku'],
            ['name', 'unit', 'base_price', 'status', 'updated_at']
        );

        $productMap = Product::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('sku', collect($products)->pluck('sku'))
            ->get()
            ->keyBy('sku');

        $stockSeeds = [
            ['sku' => '779123456001', 'stock' => 40, 'reserved' => 2, 'minimum' => 10],
            ['sku' => '779123456002', 'stock' => 8, 'reserved' => 0, 'minimum' => 12],
            ['sku' => '779123456003', 'stock' => 20, 'reserved' => 1, 'minimum' => 8],
            ['sku' => '779123456004', 'stock' => 14.5, 'reserved' => 0, 'minimum' => 5],
            ['sku' => '779123456005', 'stock' => 6, 'reserved' => 0, 'minimum' => 10],
            ['sku' => '779123456006', 'stock' => 18, 'reserved' => 0, 'minimum' => 6],
        ];

        foreach ($stockSeeds as $stockSeed) {
            $product = $productMap->get($stockSeed['sku']);
            if ($product === null) {
                continue;
            }

            BranchStock::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                ],
                [
                    'stock' => $stockSeed['stock'],
                    'reserved' => $stockSeed['reserved'],
                    'minimum' => $stockSeed['minimum'],
                ]
            );
        }

        if (Purchase::query()->where('tenant_id', $tenant->id)->count() === 0) {
            Purchase::query()->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'number' => 'OC-000001',
                'supplier_name' => 'Distribuidora Norte',
                'status' => 'sent',
                'total' => 145000,
                'expected_at' => now()->addDays(3)->toDateString(),
            ]);

            Purchase::query()->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'number' => 'OC-000002',
                'supplier_name' => 'Mayorista Sur',
                'status' => 'draft',
                'total' => 89500,
                'expected_at' => now()->addDays(5)->toDateString(),
            ]);
        }

        if (StockMovement::query()->where('tenant_id', $tenant->id)->count() === 0) {
            $movementSeeds = [
                ['sku' => '779123456003', 'type' => 'purchase_in', 'quantity' => 24, 'notes' => 'Entrada por compra inicial'],
                ['sku' => '779123456001', 'type' => 'sale_out', 'quantity' => -3, 'notes' => 'Salida por venta inicial'],
                ['sku' => '779123456002', 'type' => 'manual_adjustment_negative', 'quantity' => -2, 'notes' => 'Ajuste inicial'],
            ];

            foreach ($movementSeeds as $movementSeed) {
                $product = $productMap->get($movementSeed['sku']);
                if ($product === null) {
                    continue;
                }

                StockMovement::query()->create([
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                    'type' => $movementSeed['type'],
                    'quantity' => $movementSeed['quantity'],
                    'notes' => $movementSeed['notes'],
                ]);
            }
        }
    }
}
