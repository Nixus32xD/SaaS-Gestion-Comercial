<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $superAdminEmail = trim((string) config('app.super_admin_email'));
        if ($superAdminEmail === '') {
            $superAdminEmail = 'superadmin@example.com';
        }

        User::query()->updateOrCreate(
            ['email' => $superAdminEmail],
            [
                'business_id' => null,
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'role' => 'superadmin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $business = Business::query()->updateOrCreate(
            ['slug' => 'comercio-demo'],
            [
                'name' => 'Comercio Demo',
                'owner_name' => 'Demo Owner',
                'email' => 'demo@comercio.test',
                'phone' => '1130000000',
                'address' => 'Calle Demo 123',
                'is_active' => true,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'admin@demo.test'],
            [
                'business_id' => $business->id,
                'name' => 'Admin Demo',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $supplierRows = [
            ['name' => 'Distribuidora Norte', 'contact_name' => 'Nora Ruiz', 'phone' => '1144411100'],
            ['name' => 'Mayorista Sur', 'contact_name' => 'Martin Diaz', 'phone' => '1133311100'],
            ['name' => 'Central de Insumos', 'contact_name' => 'Julia Gomez', 'phone' => '1122211100'],
        ];

        $supplierIds = [];

        foreach ($supplierRows as $row) {
            $supplier = Supplier::query()->updateOrCreate(
                [
                    'business_id' => $business->id,
                    'name' => $row['name'],
                ],
                [
                    'contact_name' => $row['contact_name'],
                    'phone' => $row['phone'],
                    'email' => null,
                    'address' => null,
                    'notes' => null,
                ]
            );

            $supplierIds[$row['name']] = $supplier->id;
        }

        $categoryRows = [
            ['name' => 'Bebidas'],
            ['name' => 'Almacen'],
            ['name' => 'Fiambreria'],
            ['name' => 'Ferreteria'],
        ];

        $categoryIds = [];

        foreach ($categoryRows as $row) {
            $category = Category::query()->updateOrCreate(
                [
                    'business_id' => $business->id,
                    'slug' => Str::slug($row['name']),
                ],
                [
                    'name' => $row['name'],
                    'description' => null,
                    'is_active' => true,
                ]
            );

            $categoryIds[$row['name']] = $category->id;
        }

        $products = [
            [
                'name' => 'Gaseosa cola 2.25L',
                'barcode' => '779100000001',
                'sku' => 'BEB-001',
                'category' => 'Bebidas',
                'unit_type' => 'unit',
                'sale_price' => 2500,
                'cost_price' => 1700,
                'stock' => 36,
                'min_stock' => 10,
                'supplier' => 'Distribuidora Norte',
            ],
            [
                'name' => 'Yerba 1kg',
                'barcode' => '779100000002',
                'sku' => 'ALM-001',
                'category' => 'Almacen',
                'unit_type' => 'unit',
                'sale_price' => 4300,
                'cost_price' => 3200,
                'stock' => 12,
                'min_stock' => 8,
                'supplier' => 'Mayorista Sur',
            ],
            [
                'name' => 'Queso cremoso',
                'barcode' => null,
                'sku' => 'DIE-001',
                'category' => 'Fiambreria',
                'unit_type' => 'weight',
                'sale_price' => 9200,
                'cost_price' => 6800,
                'stock' => 4.5,
                'min_stock' => 6,
                'supplier' => 'Central de Insumos',
            ],
            [
                'name' => 'Tornillo 5cm',
                'barcode' => null,
                'sku' => 'FER-001',
                'category' => 'Ferreteria',
                'unit_type' => 'unit',
                'sale_price' => 120,
                'cost_price' => 60,
                'stock' => 140,
                'min_stock' => 30,
                'supplier' => 'Central de Insumos',
            ],
        ];

        foreach ($products as $row) {
            $product = Product::query()->updateOrCreate(
                [
                    'business_id' => $business->id,
                    'sku' => $row['sku'],
                ],
                [
                    'category_id' => $categoryIds[$row['category']] ?? null,
                    'supplier_id' => $supplierIds[$row['supplier']] ?? null,
                    'name' => $row['name'],
                    'slug' => Str::slug($row['name']).'-'.$business->id,
                    'description' => null,
                    'barcode' => $row['barcode'],
                    'unit_type' => $row['unit_type'],
                    'sale_price' => $row['sale_price'],
                    'cost_price' => $row['cost_price'],
                    'stock' => $row['stock'],
                    'min_stock' => $row['min_stock'],
                    'is_active' => true,
                ]
            );

            $hasInitialMovement = StockMovement::query()
                ->where('business_id', $business->id)
                ->where('product_id', $product->id)
                ->where('type', 'initial')
                ->exists();

            if (! $hasInitialMovement) {
                StockMovement::query()->create([
                    'business_id' => $business->id,
                    'product_id' => $product->id,
                    'type' => 'initial',
                    'reference_type' => Product::class,
                    'reference_id' => $product->id,
                    'quantity' => (float) $product->stock,
                    'stock_before' => 0,
                    'stock_after' => (float) $product->stock,
                    'notes' => 'Stock inicial demo',
                    'created_by' => null,
                ]);
            }
        }
    }
}
