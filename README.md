# Gestor Comercial SaaS

Sistema web para gestion comercial con foco en ventas, compras, stock y catalogo.
Construido con Laravel + Inertia + Vue.

## Stack

- PHP 8.2+
- Laravel 12
- Inertia.js (Laravel + Vue 3)
- Vite 7
- Tailwind CSS
- MySQL

## Estado funcional actual

- Autenticacion con roles `superadmin` y `admin`.
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

## Cambios recientes incluidos

- Fix en `Nueva compra`: el costo unitario ahora se sincroniza correctamente por producto seleccionado.
- Dashboard con graficos:
  - linea de tendencia ventas/compras por dia,
  - barras para top productos vendidos.
- Endurecimiento de datos para dashboard:
  - `daily_totals` se envia como arreglo plano,
  - frontend tolera `array` u `object` al leer la serie.

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
- `app/Services` reglas de negocio transaccionales (ventas/compras).
- `app/Models` entidades principales (`Business`, `Product`, `Purchase`, `Sale`, etc).
- `resources/js/Pages` vistas Inertia/Vue.
- `docs/architecture/saas-foundation.md` base arquitectonica del proyecto.

## Notas

- Timezone por defecto en `.env.example`: `America/Argentina/Buenos_Aires`.
- Cola por defecto: `database`.
- Sesion y cache por defecto: `database`.
