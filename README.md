# Gestor Comercial SaaS

Aplicacion business-first para gestionar multiples comercios desde una sola plataforma, con aislamiento estricto por `business_id`.
Construido con Laravel + Inertia + Vue.

## Stack

- PHP 8.2+
- Laravel 12
- Inertia.js (Laravel + Vue 3)
- Vite 7
- Tailwind CSS
- MySQL

## Enfoque actual

- Una sola aplicacion y una sola base de datos compartida.
- Aislamiento por comercio usando `business_id`.
- Onboarding de comercios solo desde panel `superadmin`.
- Usuarios internos del negocio con roles simples `admin` y `staff`.
- Sin tenancy avanzada, memberships, tenant switching ni multi-sucursal compleja en el flujo principal.

## Estado funcional actual

- Autenticacion con roles `superadmin` y `admin`.
- Gestion de usuarios internos del comercio (`admin` y `staff`).
- Gestion de comercios (panel de superadmin).
- Dashboard operativo:
  - ventas del dia y del mes,
  - totales de productos/proveedores,
  - tendencia diaria de ventas/compras (14 dias),
  - ranking visual de productos mas vendidos,
  - alertas de stock bajo,
  - ultimas ventas y compras.
- Catalogo de productos:
  - alta/edicion,
  - costo/precio venta,
  - unidad/peso,
  - stock minimo.
- Proveedores:
  - alta/edicion,
  - asociacion a productos/compras.
- Compras:
  - nueva compra por busqueda o scanner (barcode/SKU),
  - actualizacion de stock y costo del producto,
  - historial y detalle de compras.
- Ventas:
  - registro de venta,
  - descuento,
  - actualizacion de stock,
  - historial y detalle de ventas.
- Movimientos de stock:
  - entradas por compra,
  - salidas por venta,
  - stock inicial demo.

## Consolidacion aplicada

- El runtime principal opera solo sobre `business_id`.
- `sale_items` y `purchase_items` ahora tienen `business_id`.
- Las ventas y compras usan secuencias por negocio para numerar documentos.
- Las props compartidas de Inertia quedaron limitadas a datos livianos.
- El flujo tenant/domain legacy quedo fuera del circuito principal.

## Requisitos

- PHP 8.2 o superior
- Composer
- Node.js 20+ y npm
- MySQL 8+

## Instalacion

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configura base de datos en `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gestor_comercial_saas
DB_USERNAME=root
DB_PASSWORD=
```

Ejecuta migraciones y seeders:

```bash
php artisan migrate
php artisan db:seed
```

Instala frontend:

```bash
npm install
```

## Ejecucion en desarrollo

Backend + frontend (dos terminales):

```bash
php artisan serve
npm run dev
```

O con el comando compuesto de Composer:

```bash
composer run dev
```

## Build de produccion

```bash
npm run build
```

## Pruebas

```bash
php artisan test
```

El entorno de testing usa MySQL y toma configuracion desde `.env.testing`.

## Credenciales demo (DatabaseSeeder)

- Super Admin:
  - email: `superadmin@example.com` (o `SUPER_ADMIN_EMAIL` en `.env`)
  - password: `password`
- Admin comercio demo:
  - email: `admin@demo.test`
  - password: `password`

## Rutas principales

- `/dashboard`
- `/products`
- `/suppliers`
- `/purchases`
- `/sales`
- `/admin/businesses` (solo superadmin)

## Estructura util

- `app/Http/Controllers` controladores por modulo.
- `app/Services` reglas de negocio transaccionales y numeradores por negocio.
- `app/Models` entidades principales (`Business`, `Product`, `Purchase`, `Sale`, etc).
- `resources/js/Pages` vistas Inertia/Vue.
- `docs/architecture/business-first-mvp.md` resumen de la arquitectura business-first.

## Notas

- Timezone por defecto en `.env.example`: `America/Argentina/Buenos_Aires`.
- Cola por defecto: `database`.
- Sesion y cache por defecto: `database`.
