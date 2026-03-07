<?php

namespace Database\Seeders;

use App\Domain\Rbac\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        $permissions = [
            ['key' => 'dashboard.view', 'name' => 'Ver dashboard', 'group' => 'dashboard'],
            ['key' => 'users.manage', 'name' => 'Gestionar usuarios', 'group' => 'users'],
            ['key' => 'roles.manage', 'name' => 'Gestionar roles', 'group' => 'roles'],
            ['key' => 'branches.manage', 'name' => 'Gestionar sucursales', 'group' => 'branches'],
            ['key' => 'catalog.view', 'name' => 'Ver catálogo', 'group' => 'catalog'],
            ['key' => 'catalog.manage', 'name' => 'Gestionar catálogo', 'group' => 'catalog'],
            ['key' => 'inventory.view', 'name' => 'Ver inventario', 'group' => 'inventory'],
            ['key' => 'inventory.manage', 'name' => 'Gestionar inventario', 'group' => 'inventory'],
            ['key' => 'purchases.view', 'name' => 'Ver compras', 'group' => 'purchases'],
            ['key' => 'purchases.manage', 'name' => 'Gestionar compras', 'group' => 'purchases'],
            ['key' => 'sales.view', 'name' => 'Ver ventas', 'group' => 'sales'],
            ['key' => 'sales.manage', 'name' => 'Gestionar ventas', 'group' => 'sales'],
            ['key' => 'cash.view', 'name' => 'Ver caja', 'group' => 'cash'],
            ['key' => 'cash.manage', 'name' => 'Gestionar caja', 'group' => 'cash'],
            ['key' => 'customers.view', 'name' => 'Ver clientes', 'group' => 'customers'],
            ['key' => 'customers.manage', 'name' => 'Gestionar clientes', 'group' => 'customers'],
            ['key' => 'suppliers.view', 'name' => 'Ver proveedores', 'group' => 'suppliers'],
            ['key' => 'suppliers.manage', 'name' => 'Gestionar proveedores', 'group' => 'suppliers'],
            ['key' => 'reports.view', 'name' => 'Ver reportes', 'group' => 'reports'],
            ['key' => 'settings.manage', 'name' => 'Gestionar configuración', 'group' => 'settings'],
        ];

        $payload = collect($permissions)->map(
            fn (array $permission): array => [
                ...$permission,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        )->all();

        Permission::query()->upsert($payload, ['key'], ['name', 'group', 'updated_at']);
    }
}
