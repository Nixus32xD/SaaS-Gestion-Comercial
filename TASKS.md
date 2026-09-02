# TASKS.md — Gestor Comercial SaaS

## Estado actual

El proyecto ya superó la etapa de MVP básico.

Actualmente incluye:

* arquitectura multi-comercio mediante `business_id`;
* usuarios internos y administración por comercio;
* productos, categorías y stock;
* lotes, vencimientos y movimientos;
* compras y proveedores;
* ventas y POS;
* clientes;
* cuentas corrientes;
* cobranzas;
* dashboard;
* facturación electrónica;
* PDF y datos fiscales;
* notificaciones operativas;
* integración con Mercado Pago Point;
* webhooks firmados e idempotentes;
* reserva de stock para pagos Point;
* cancelación y expiración automática de operaciones Point.

El objetivo actual es evolucionar el sistema hacia una plataforma comercial multi-sucursal e integrable.

---

# Prioridad actual

## Fase 1 — Cierre y robustez de Mercado Pago Point

* [x] Crear órdenes Point.
* [x] Consultar estado de órdenes.
* [x] Webhook firmado.
* [x] Idempotencia de webhooks.
* [x] Separar credenciales por `business_id`.
* [x] Reserva de stock durante pagos pendientes.
* [x] Consumir stock únicamente después de aprobación.
* [x] Liberar reserva en rechazo/cancelación.
* [x] Evitar facturación antes de aprobación.
* [x] Permitir cancelación manual.
* [x] Expirar operaciones pendientes después del timeout.
* [x] Consultar Mercado Pago antes de liberar reservas vencidas.
* [x] Permitir nuevos intentos con nuevas idempotency keys.
* [ ] Detectar aprobación remota posterior a cancelación/expiración local.
* [ ] Implementar estado de conciliación requerida.
* [ ] Validar workflow completo mediante CI.

---

# Fase 2 — Calidad y automatización

* [ ] Configurar GitHub Actions.
* [ ] Ejecutar backend tests en cada push/PR.
* [ ] Ejecutar build frontend en cada push/PR.
* [ ] Revisar cobertura de tests críticos.
* [ ] Mantener tests para stock, ventas, pagos y facturación.
* [ ] Revisar logs y manejo de excepciones de integraciones externas.
* [ ] Revisar jobs fallidos y estrategia de retry.
* [ ] Documentar proceso de deploy y rollback.

---

# Fase 3 — Multi-sucursal

Objetivo principal después de cerrar Mercado Pago Point.

Implementar una entidad de sucursal dentro de cada comercio.

Modelo conceptual:

```text
Business
 ├── Branch
 ├── Branch
 └── Branch
```

Cada sucursal debe pertenecer siempre a un `business_id`.

## Funcionalidades

* [ ] Crear modelo `Branch`.
* [ ] CRUD de sucursales.
* [ ] Selección de sucursal activa.
* [ ] Asociar ventas a sucursal.
* [ ] Asociar compras a sucursal.
* [ ] Asociar cajas/destinos de pago a sucursal.
* [ ] Asociar terminales Point a sucursal.
* [ ] Dashboard filtrable por sucursal.
* [ ] Vista global consolidada del comercio.
* [ ] Permisos de usuario por sucursal.

---

# Fase 4 — Stock por sucursal

No duplicar productos innecesariamente.

Mantener catálogo de productos a nivel comercio y separar existencias.

Modelo conceptual:

```text
Product
   ↓
BranchStock
   ├── branch_id
   ├── product_id
   ├── stock
   ├── reserved_stock
   └── min_stock
```

## Funcionalidades

* [ ] Stock independiente por sucursal.
* [ ] Stock reservado independiente por sucursal.
* [ ] Alertas de stock por sucursal.
* [ ] Movimientos de stock con `branch_id`.
* [ ] Lotes por sucursal.
* [ ] Compras ingresadas a una sucursal.
* [ ] Ventas descontadas de la sucursal correspondiente.
* [ ] Point reservando stock de la sucursal correcta.

---

# Fase 5 — Transferencias entre sucursales

* [ ] Crear transferencias de inventario.
* [ ] Estado `pending`.
* [ ] Estado `in_transit`.
* [ ] Estado `received`.
* [ ] Cancelación.
* [ ] Auditoría.
* [ ] Responsable que envía.
* [ ] Responsable que recibe.
* [ ] Movimientos de salida y entrada.
* [ ] Evitar diferencias de stock por transferencias incompletas.

---

# Fase 6 — Caja y operación diaria

* [ ] Apertura de caja.
* [ ] Cierre de caja.
* [ ] Saldo inicial.
* [ ] Ingresos.
* [ ] Egresos.
* [ ] Ventas por método de pago.
* [ ] Diferencia esperada vs real.
* [ ] Caja por sucursal.
* [ ] Caja por usuario/turno.
* [ ] Reportes de cierre.

---

# Fase 7 — Facturación y fiscalización avanzada

* [x] Emisión electrónica básica.
* [x] CAE/CAA según arquitectura actual.
* [x] PDF fiscal.
* [x] IVA de ventas.
* [ ] IVA de compras.
* [ ] Dashboard fiscal.
* [ ] Resumen mensual IVA ventas.
* [ ] Resumen mensual IVA compras.
* [ ] Diferencia débito/crédito fiscal.
* [ ] Exportaciones contables.
* [ ] Reportes por sucursal.
* [ ] Mejorar conciliación de errores fiscales.

---

# Fase 8 — Tienda online

Implementar después de estabilizar multi-sucursal y stock por sucursal.

## Catálogo

* [ ] Catálogo público.
* [ ] Productos habilitables individualmente.
* [ ] Categorías públicas.
* [ ] Imágenes.
* [ ] Precios.
* [ ] Stock disponible.

## Pedidos

* [ ] Carrito.
* [ ] Checkout.
* [ ] Cliente.
* [ ] Dirección.
* [ ] Retiro en local.
* [ ] Envío.
* [ ] Estado del pedido.

## Stock

La tienda debe utilizar:

```text
stock disponible
=
stock físico
-
stock reservado
```

y posteriormente determinar desde qué sucursal se prepara el pedido.

---

# Fase 9 — Pagos online

* [ ] Mercado Pago Checkout/Orders online.
* [ ] QR dinámico.
* [ ] Reserva de stock durante checkout.
* [ ] Webhooks.
* [ ] Confirmación de pago.
* [ ] Cancelación.
* [ ] Reembolso.
* [ ] Conciliación de pagos.
* [ ] Evitar doble descuento.

Reutilizar los servicios y patrones implementados para Mercado Pago Point siempre que sea razonable.

---

# Fase 10 — Integraciones externas

## PedidosYa

* [ ] Investigar API disponible y requisitos comerciales.
* [ ] Vincular catálogo.
* [ ] Recibir pedidos.
* [ ] Mapear productos.
* [ ] Actualizar estados.
* [ ] Reservar stock.
* [ ] Confirmar pedido.
* [ ] Registrar comisión.
* [ ] Asociar pedido a sucursal.

## Otras integraciones posibles

* [ ] WhatsApp Business.
* [ ] Tienda propia.
* [ ] Mercado Libre.
* [ ] Delivery propio.
* [ ] APIs contables.
* [ ] Exportaciones para estudios contables.

---

# Fase 11 — Reportes y analítica

* [ ] Ventas por día/semana/mes.
* [ ] Ventas por sucursal.
* [ ] Ventas por usuario.
* [ ] Ventas por método de pago.
* [ ] Rentabilidad.
* [ ] Margen por producto.
* [ ] Productos más vendidos.
* [ ] Productos sin rotación.
* [ ] Stock inmovilizado.
* [ ] Compras por proveedor.
* [ ] Deudas de clientes.
* [ ] Flujo de caja.
* [ ] Comparación entre sucursales.

---

# Principios técnicos

Toda nueva funcionalidad debe mantener estas reglas.

## Multi-comercio

Toda información comercial debe pertenecer a un:

```text
business_id
```

Nunca permitir acceso cruzado entre comercios.

## Multi-sucursal

Cuando se implemente:

```text
business_id
→ branch_id
```

`business_id` seguirá siendo el límite principal de tenancy.

`branch_id` será una subdivisión operativa del comercio.

## Stock

Mantener separación entre:

```text
stock físico
reserved_stock
stock disponible
```

Nunca permitir doble consumo de stock.

## Pagos

Las integraciones externas deben ser:

* idempotentes;
* auditables;
* tolerantes a webhooks duplicados;
* tolerantes a timeouts;
* reconciliables.

## Operaciones externas

Nunca asumir que:

```text
timeout HTTP = operación fallida
```

Cuando exista incertidumbre, consultar/reconciliar antes de repetir una operación sensible.

## Base de datos

Usar:

* transacciones;
* `lockForUpdate()` cuando corresponda;
* constraints;
* índices;
* foreign keys;
* unique keys para idempotencia.

## Testing

Toda funcionalidad crítica nueva debe incluir tests para:

* caso exitoso;
* error;
* duplicado;
* concurrencia relevante;
* cancelación;
* estado inconsistente.

---

# Próximo objetivo

Finalizar completamente el ciclo de Mercado Pago Point y CI.

Después comenzar:

```text
MULTI-SUCURSAL
```

Prioridad inicial:

1. diseño de modelo de datos;
2. `Branch`;
3. sucursal activa;
4. stock por sucursal;
5. ventas y compras por sucursal;
6. Point por sucursal;
7. dashboard consolidado.
