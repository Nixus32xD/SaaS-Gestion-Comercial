# Gestor Comercial SaaS

SaaS de gestión comercial para pequeños y medianos comercios de Argentina. Centraliza productos, stock, compras, ventas, clientes, cuentas corrientes, facturación electrónica y cobros con Mercado Pago Point.

La aplicación opera con una única base de datos y aísla toda la información comercial mediante `business_id`.

## Stack

- PHP 8.2+
- Laravel 12
- MySQL 8+
- Inertia.js + Vue 3
- Tailwind CSS + Vite
- Colas y cache persistidas en base de datos

## Funcionalidades

- Gestión multi-comercio: panel de superadmin, configuración comercial y usuarios internos con roles `admin` y `staff`.
- Productos y stock: catálogo global, categorías, SKU/código de barras, unidades, lotes, vencimientos, alertas y movimientos.
- Compras y proveedores: carga de compras, actualización de costos/stock e historial.
- Ventas y POS: descuentos, opciones rápidas, comprobantes, historial, impresión y búsqueda por scanner.
- Clientes y cuentas corrientes: saldos, cobranzas y recordatorios por WhatsApp o email.
- Facturación electrónica: configuración por comercio, emisión, conciliación y descarga de comprobantes PDF.
- Notificaciones operativas y de mantenimiento configurables por comercio.
- Mercado Pago Point: cobros presenciales con creación, consulta y cancelación de órdenes; webhook firmado e idempotente.

## Ciclo de cobro con Mercado Pago Point

Al iniciar un cobro Point, la venta queda pendiente y el stock se reserva; no se descuenta ni se emite un comprobante fiscal todavía.

1. Se crea la orden en Point y se reserva stock.
2. Cuando Mercado Pago confirma el pago, el webhook actualiza el cobro, consume la reserva y habilita la emisión fiscal.
3. Si se rechaza, cancela o vence, se libera la reserva sin descontar stock.
4. Las notificaciones repetidas y los reintentos son idempotentes: no duplican movimientos ni comprobantes.
5. Una tarea programada revisa las órdenes pendientes cada minuto y expira reservas que superen 10 minutos, después de consultar su estado remoto.

La configuración de credenciales y terminal de Point se administra por comercio desde `/integrations/mercadopago`. No versionar tokens, secretos de webhook ni credenciales reales.

## Requisitos

- PHP 8.2 o superior
- Composer
- Node.js 20+ y npm
- MySQL 8+

## Instalación

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configurar la conexión en `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gestor_comercial_saas
DB_USERNAME=root
DB_PASSWORD=
```

Luego ejecutar migraciones, datos de ejemplo y dependencias de frontend:

```bash
php artisan migrate
php artisan db:seed
npm install
```

## Desarrollo

En dos terminales:

```bash
php artisan serve
npm run dev
```

O mediante el comando compuesto, que además inicia el worker de colas y el visor de logs:

```bash
composer run dev
```

## Producción

Compilar los assets:

```bash
npm run build
```

Mantener un worker de colas y el scheduler activos:

```bash
php artisan queue:work
php artisan schedule:work
```

En un servidor, normalmente el scheduler se invoca cada minuto con cron:

```cron
* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

El scheduler procesa alertas operativas, recordatorios de mantenimiento y la expiración de reservas Point.

Para Mercado Pago Point también se debe configurar como webhook la URL pública:

```text
POST https://tu-dominio.com/webhooks/mercadopago/orders
```

## Pruebas

```bash
php artisan test
```

El entorno de pruebas usa MySQL y toma su configuración desde `.env.testing`.

## Credenciales demo

- Superadmin: `superadmin@example.com` (o `SUPER_ADMIN_EMAIL` en `.env`) / `password`
- Admin del comercio demo: `admin@demo.test` / `password`

## Rutas principales

- `/dashboard`
- `/products`, `/categories`
- `/suppliers`, `/purchases`
- `/customers`, `/customer-accounts`
- `/sales`
- `/electronic-billing`
- `/integrations/mercadopago` (admin del comercio)
- `/notifications` (admin del comercio)
- `/admin/businesses` (superadmin)

## Estructura

- `app/Http/Controllers`: controladores por módulo.
- `app/Services`: reglas de negocio transaccionales, pagos, stock y fiscalización.
- `app/Models`: entidades con aislamiento por comercio.
- `app/Jobs` y `app/Console/Commands`: procesamiento asíncrono y tareas programadas.
- `resources/js/Pages`: vistas Inertia/Vue.
- `tests/Feature`: pruebas funcionales.
- `docs/architecture/business-first-mvp.md`: resumen de la arquitectura business-first.

## Notas operativas

- Timezone por defecto: `America/Argentina/Buenos_Aires`.
- El driver de cola, sesión y cache por defecto es `database`.
- Las ventas, compras, productos, clientes y pagos deben operar siempre dentro del contexto del comercio autenticado.
