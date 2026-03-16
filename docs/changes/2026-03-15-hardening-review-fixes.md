# Hardening Review Fixes

Fecha: 2026-03-15

## Contexto

Se revisaron y corrigieron cuatro riesgos detectados en la auditoria del MVP:

1. El POS de ventas dependia de una carga inicial limitada de productos.
2. Las compras con producto nuevo podian chocar con `sku` o `barcode` existentes y terminar en error 500.
3. La base no reforzaba del todo la integridad por `business_id` en `sale_items` y `purchase_items`.
4. Las alertas de vencimiento mostraban falsos positivos cuando el producto ya no tenia stock disponible.

## Cambios realizados

### 1. Busqueda remota en ventas

Antes:

- La pantalla `Sales/Create` recibia solo un bloque acotado de productos.
- Si el comercio tenia mas productos que ese limite, parte del catalogo quedaba fuera del flujo de venta.

Ahora:

- Se agrego la ruta `sales.products.search`.
- `SaleController` expone una busqueda remota por nombre, barcode o SKU.
- `Sales/Create.vue` consulta esa ruta mientras se escribe y usa los resultados para agregar productos al carrito.
- El formulario sigue enviando solo los datos minimos requeridos al backend.

Archivos:

- `routes/web.php`
- `app/Http/Controllers/Sales/SaleController.php`
- `resources/js/Pages/Sales/Create.vue`
- `tests/Feature/Sales/SaleProductSearchTest.php`

### 2. Validacion de duplicados al crear producto nuevo desde compras

Antes:

- Una compra podia intentar crear un producto nuevo con `sku` o `barcode` ya usados por el mismo comercio.
- La validacion no interceptaba ese caso y se dependia del `unique` de base de datos.
- El resultado practico podia ser una excepcion de BD en lugar de un error de formulario.

Ahora:

- `PurchaseService` valida `sku` y `barcode` antes de crear el producto.
- Se valida contra productos existentes del mismo negocio, incluyendo soft deletes.
- Tambien se valida que no se repitan dentro de la misma compra.
- Si hay conflicto, se devuelve error de validacion asociado al item puntual.

Archivos:

- `app/Services/PurchaseService.php`
- `tests/Feature/Operations/PurchaseFlowTest.php`

### 3. Integridad extra por `business_id` en items operativos

Antes:

- `sale_items` y `purchase_items` tenian `business_id`, pero la BD no obligaba a que coincidiera con la venta, compra o producto relacionado.
- El aislamiento dependia casi por completo del runtime.

Ahora:

- Se agrego una migracion nueva con indices compuestos y foreign keys compuestas.
- Esto evita insertar items que mezclen referencias de negocios distintos.

Archivos:

- `database/migrations/2026_03_15_000600_add_business_integrity_constraints_to_operation_items.php`
- `tests/Feature/BusinessIntegrityTest.php`

## 4. Alertas de vencimiento mas realistas

Antes:

- El servicio de alertas podia listar lotes vencidos o proximos a vencer aunque el producto ya no tuviera stock disponible.

Ahora:

- Las alertas solo consideran productos activos con stock mayor a cero.
- Esto no resuelve trazabilidad por lote, pero reduce falsos positivos visibles en dashboard.

Archivos:

- `app/Services/ProductExpirationAlertService.php`
- `tests/Feature/DashboardTest.php`

## Que se tuvo que hacer para arreglarlo

En terminos practicos, el arreglo requirio:

1. Separar la busqueda de productos del payload inicial de la pantalla de ventas.
2. Llevar validaciones de identidad de producto al servicio de compras, no solo al flujo de alta manual.
3. Mover parte del blindaje de aislamiento a la base de datos con constraints compuestas.
4. Ajustar la logica de dashboard para que las alertas no muestren productos sin stock.
5. Agregar tests de regresion para que estos casos no vuelvan a romperse.

## Verificacion realizada

Se ejecuto:

- `php artisan test`
- `npm run build`

Resultado:

- 56 tests pasando
- 204 assertions
- build de frontend exitoso

## Limites que todavia quedan

Estos cambios mejoran bastante el estado actual, pero no convierten el modulo en trazabilidad completa por lote.

Sigue pendiente si alguna vez se quiere resolver al 100% el problema de vencimientos:

- guardar stock remanente por lote
- descontar ventas por lote
- calcular alertas contra saldo real de cada lote y no solo contra `purchase_items`

Para el MVP actual, el estado queda razonable y bastante mas seguro que antes.
