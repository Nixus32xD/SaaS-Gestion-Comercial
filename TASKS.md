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
* sucursales, stock, lotes y movimientos por sucursal;
* transferencias inmediatas de inventario entre sucursales;
* caja operativa por sucursal;
* IVA Compras y resumen fiscal mensual de referencia.

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
* [x] Detectar aprobación remota posterior a cancelación/expiración local.
* [x] Implementar estado de conciliación requerida.
* [x] Validar workflow completo mediante CI.

---

# Fase 2 — Calidad y automatización

* [x] Configurar GitHub Actions.
* [x] Ejecutar backend tests en cada push/PR.
* [x] Ejecutar build frontend en cada push/PR.
* [x] Mantener tests críticos para stock, ventas, pagos y facturación.
* [x] Registrar fallos de integraciones con contexto operativo.
* [x] Definir retries acotados para jobs críticos.
* [ ] Revisar cobertura de tests críticos de forma periódica.
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

* [x] Crear modelo `Branch`.
* [x] CRUD de sucursales.
* [x] Selección de sucursal activa.
* [x] Asociar ventas a sucursal.
* [x] Asociar compras a sucursal.
* [x] Asociar cajas/destinos de pago a sucursal.
* [x] Asociar terminales Point a sucursal.
* [x] Dashboard filtrable por sucursal.
* [x] Vista global consolidada del comercio.
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

* [x] Stock independiente por sucursal.
* [x] Stock reservado independiente por sucursal.
* [x] Alertas de stock por sucursal.
* [x] Movimientos de stock con `branch_id`.
* [x] Lotes por sucursal.
* [x] Compras ingresadas a una sucursal.
* [x] Ventas descontadas de la sucursal correspondiente.
* [x] Point reservando stock de la sucursal correcta.

---

# Fase 5 — Transferencias entre sucursales

* [x] Crear transferencias de inventario inmediatas, transaccionales e idempotentes.
* [x] Auditar responsable, sucursales, producto, lotes y referencia común.
* [x] Registrar movimientos de salida y entrada.
* [x] Evitar diferencias mediante locks, reserva de stock y rollback.
* [ ] Evaluar un flujo futuro de recepción (`pending` / `in_transit` / `received`) si la operación deja de ser inmediata.

---

# Fase 6 — Caja y operación diaria

* [x] Apertura y cierre de caja.
* [x] Saldo inicial, ingresos y egresos auditables.
* [x] Integración sólo con la porción en efectivo de las ventas.
* [x] Diferencia esperada vs. real y reportes de cierre.
* [x] Caja operativa por sucursal con usuario responsable.

---

# Fase 7 — Facturación y fiscalización avanzada

* [x] Emisión electrónica básica.
* [x] CAE/CAA según arquitectura actual.
* [x] PDF fiscal.
* [x] IVA de ventas.
* [x] IVA de compras con desglose por alícuota y crédito fiscal.
* [x] Dashboard fiscal mensual de referencia.
* [x] Resumen mensual IVA ventas (reutilizando el libro existente de apiArca).
* [x] Resumen mensual IVA compras.
* [x] Diferencia débito/crédito fiscal estimada.
* [x] Exportación CSV del Libro IVA Compras.
* [x] Reportes consolidados y por sucursal.
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

Completar la asignación manual de `owner_user_id` para Businesses históricos y
agregar pruebas de regresión RBAC cuando se repare la cadena de migraciones de
testing que actualmente duplica `sale_fiscal_documents`.
