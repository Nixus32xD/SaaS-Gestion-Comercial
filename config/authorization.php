<?php

return [
    /*
     * Permission codes are the authorization contract. Roles are only reusable
     * groupings of these codes, never a condition used by the application.
     */
    'permissions' => [
        'dashboard.view' => 'Panel',
        'categories.view' => 'Inventario', 'categories.manage' => 'Inventario',
        'products.view' => 'Inventario', 'products.create' => 'Inventario', 'products.update' => 'Inventario',
        'inventory.adjust' => 'Inventario', 'inventory.transfer' => 'Inventario',
        'suppliers.view' => 'Compras', 'suppliers.manage' => 'Compras',
        'purchases.view' => 'Compras', 'purchases.create' => 'Compras',
        'sales.view' => 'Ventas', 'sales.create' => 'Ventas', 'sales.receipts.manage' => 'Ventas',
        'cash_register.view' => 'Caja', 'cash_register.open' => 'Caja', 'cash_register.movements' => 'Caja', 'cash_register.close' => 'Caja', 'cash_register.adjust' => 'Caja',
        'customers.view' => 'Clientes', 'customers.manage' => 'Clientes', 'accounts_receivable.view' => 'Clientes', 'accounts_receivable.collect' => 'Clientes', 'customers.remind' => 'Clientes',
        'fiscal.view' => 'Fiscal', 'fiscal.issue' => 'Fiscal', 'fiscal.reconcile' => 'Fiscal', 'fiscal.reports' => 'Fiscal',
        'fiscal.settings.view' => 'Configuración fiscal', 'fiscal.settings.manage' => 'Configuración fiscal', 'fiscal.credentials.manage' => 'Configuración fiscal',
        'mercadopago.payments.view' => 'Mercado Pago', 'mercadopago.point.use' => 'Mercado Pago',
        'mercadopago.settings.view' => 'Configuración Mercado Pago', 'mercadopago.settings.manage' => 'Configuración Mercado Pago',
        'users.view' => 'Usuarios y permisos', 'users.manage' => 'Usuarios y permisos',
        'roles.view' => 'Usuarios y permisos', 'roles.assign' => 'Usuarios y permisos', 'roles.manage' => 'Usuarios y permisos',
        'branches.view' => 'Sucursales', 'branches.manage' => 'Sucursales',
        'notifications.manage' => 'Configuración', 'sales.settings.manage' => 'Configuración',
    ],

    // New businesses receive copies of these protected templates.
    'roles' => [
        'administrator' => ['name' => 'Administrador', 'permissions' => ['*']],
        'user_manager' => ['name' => 'Gestor de usuarios', 'permissions' => ['users.view', 'users.manage', 'roles.view', 'roles.assign']],
        'sales' => ['name' => 'Ventas', 'permissions' => ['dashboard.view', 'sales.view', 'sales.create', 'sales.receipts.manage', 'products.view', 'customers.view', 'customers.manage', 'mercadopago.payments.view', 'mercadopago.point.use']],
        'cashier' => ['name' => 'Caja', 'permissions' => ['dashboard.view', 'cash_register.view', 'cash_register.open', 'cash_register.movements', 'cash_register.close', 'sales.view', 'mercadopago.payments.view', 'mercadopago.point.use']],
        'purchases' => ['name' => 'Compras', 'permissions' => ['dashboard.view', 'suppliers.view', 'suppliers.manage', 'purchases.view', 'purchases.create', 'products.view']],
        'inventory' => ['name' => 'Inventario', 'permissions' => ['dashboard.view', 'categories.view', 'categories.manage', 'products.view', 'products.create', 'products.update', 'inventory.adjust', 'inventory.transfer']],
        'finance' => ['name' => 'Finanzas', 'permissions' => ['dashboard.view', 'sales.view', 'purchases.view', 'cash_register.view', 'accounts_receivable.view', 'accounts_receivable.collect', 'customers.view']],
        'fiscal' => ['name' => 'Fiscal', 'permissions' => ['dashboard.view', 'sales.view', 'purchases.view', 'fiscal.view', 'fiscal.issue', 'fiscal.reconcile', 'fiscal.reports']],
        'reports' => ['name' => 'Reportes', 'permissions' => ['dashboard.view', 'sales.view', 'purchases.view', 'fiscal.reports', 'accounts_receivable.view']],
        'supervisor' => ['name' => 'Supervisor', 'permissions' => ['dashboard.view', 'sales.view', 'sales.create', 'products.view', 'inventory.adjust', 'cash_register.view', 'cash_register.open', 'cash_register.movements', 'cash_register.close', 'customers.view', 'purchases.view']],
    ],
];
