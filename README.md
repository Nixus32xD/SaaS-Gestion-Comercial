# Gestor Comercial SaaS

SaaS de gestión comercial para pequeños y medianos comercios de Argentina. Centraliza productos, stock, compras, ventas, clientes, cuentas corrientes, facturación electrónica y cobros con Mercado Pago Point.

La aplicación opera con una única base de datos, aísla la información comercial mediante `business_id` y utiliza `branch_id` como contexto operativo de cada sucursal.

## Stack

- PHP 8.2+
- Laravel 12
- MySQL 8+
- Inertia.js + Vue 3
- Tailwind CSS + Vite
- Colas y cache persistidas en base de datos

## Funcionalidades

- Gestión multi-comercio: panel de superadmin, configuración comercial y usuarios internos con roles `admin` y `staff`.
- Productos y stock: catálogo global, categorías, SKU/código de barras, unidades, lotes, vencimientos, alertas, movimientos y stock reservado por sucursal.
- Compras y proveedores: carga de compras, actualización de costos/stock, historial y comprobantes fiscales opcionales para IVA Compras.
- Ventas y POS: descuentos, opciones rápidas, comprobantes, historial, impresión y búsqueda por scanner.
- Operación por sucursal: selector de sucursal activa, dashboard consolidado, transferencias transaccionales con FEFO y trazabilidad de lotes.
- Caja: apertura y cierre por sucursal, ingresos/egresos auditables y afectación exclusiva de la porción en efectivo de cada venta.
- Clientes y cuentas corrientes: saldos, cobranzas y recordatorios por WhatsApp o email.
- Facturación electrónica: identidades fiscales por sucursal, emisión, conciliación y descarga de comprobantes PDF.
- Fiscal: libro IVA Ventas existente, libro IVA Compras, resumen mensual de débito/crédito fiscal y exportación CSV por sucursal o consolidada.
- Notificaciones operativas y de mantenimiento configurables por comercio.
- Mercado Pago Point: cobros presenciales con creación, consulta y cancelación de órdenes; webhook firmado, idempotente y conciliable.

## Ciclo de cobro con Mercado Pago Point

Al iniciar un cobro Point, la venta queda pendiente y el stock se reserva; no se descuenta ni se emite un comprobante fiscal todavía.

1. Se crea la orden en Point y se reserva stock.
2. Cuando Mercado Pago confirma el pago, el webhook actualiza el cobro, consume la reserva y habilita la emisión fiscal.
3. Si se rechaza, cancela o vence, se libera la reserva sin descontar stock.
4. Las notificaciones repetidas y los reintentos son idempotentes: no duplican movimientos ni comprobantes.
5. Una tarea programada revisa las órdenes pendientes cada minuto y expira reservas que superen 10 minutos, después de consultar su estado remoto.

Los jobs críticos tienen reintentos progresivos y acotados. Si un webhook no puede procesarse, conserva su evento y contexto para reintento; los fallos definitivos se registran como `critical_job_failed`.

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
npm ci
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
php artisan queue:work --queue=default,notifications --max-time=3600
php artisan schedule:work
```

El worker debe consumir `default` y `notifications`: los webhooks Point se procesan en la cola por defecto y los correos operativos usan `notifications`. Los jobs críticos tienen reintentos progresivos y acotados; revisar `php artisan queue:failed` y `storage/logs/laravel.log` ante una alerta `critical_job_failed`, y usar `php artisan queue:retry <uuid>` únicamente después de revisar el contexto del pago o comprobante.

En un servidor, normalmente el scheduler se invoca cada minuto con cron:

```cron
* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

El scheduler procesa alertas operativas, recordatorios de mantenimiento y la expiración de reservas Point.

En producción con más de una instancia, configurar un cache compartido (Redis o base de datos) para que `onOneServer()` coordine los comandos. En Laravel Cloud, crear un worker permanente para `default,notifications` y un scheduler que ejecute `php artisan schedule:run` cada minuto.

Para Mercado Pago Point también se debe configurar como webhook la URL pública:

```text
POST https://tu-dominio.com/webhooks/mercadopago/orders
```

## Pruebas

```bash
php artisan test
npm run build
```

El entorno de pruebas usa MySQL y toma su configuración desde `.env.testing`.

Para pruebas E2E locales se usa Playwright. El setup reinicia exclusivamente la
base configurada para `testing`, por lo que nunca debe apuntarse al entorno de
producción:

```bash
npm run test:e2e
```

## Credenciales demo

- Superadmin: `superadmin@example.com` (o `SUPER_ADMIN_EMAIL` en `.env`) / `password`
- Admin del comercio demo: `admin@demo.test` / `password`

## Rutas principales

- `/dashboard`
- `/products`, `/categories`
- `/suppliers`, `/purchases`
- `/customers`, `/customer-accounts`
- `/sales`
- `/cash-register`
- `/inventory/transfers`
- `/electronic-billing`
- `/fiscal/iva`
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
