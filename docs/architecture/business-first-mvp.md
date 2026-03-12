# Gestor Comercial - Arquitectura Business-First

## Objetivo

Consolidar el MVP sobre un modelo simple y mantenible basado en `business_id`, con una sola aplicacion, una sola base de datos compartida y aislamiento estricto de datos por comercio.

## Principios

- `business_id` es el eje unico de aislamiento del negocio.
- No se usa tenancy avanzada en el runtime principal.
- `superadmin` administra comercios y usuarios globales.
- Cada comercio opera con usuarios internos simples (`admin`, `staff`).
- Las reglas criticas de compras, ventas y stock viven en servicios transaccionales.

## Contexto activo

- El middleware `business` resuelve el comercio del usuario autenticado.
- `CurrentBusiness` expone el comercio activo al resto de la app.
- No hay `tenant_id`, `branch_id` ni cambio de contexto por membresias.

## Modelo de datos

### Tablas globales

- `businesses`
- `users` para `superadmin`
- tablas tecnicas (`cache`, `jobs`, `sessions`, `password_reset_tokens`)

### Tablas del comercio

- `users` con `business_id`
- `products`
- `suppliers`
- `sales`
- `sale_items`
- `purchases`
- `purchase_items`
- `stock_movements`
- `business_document_sequences`

## Reglas de implementacion

- Todas las consultas operativas del comercio filtran por `business_id`.
- Los controladores se mantienen delgados.
- La validacion vive en `FormRequest`.
- Las operaciones sensibles usan transacciones y locking razonable.
- Los numeradores de ventas y compras usan secuencias por negocio.
- Inertia comparte solo datos livianos; las metricas se resuelven en pantallas puntuales.

## Estado del MVP

### Alta prioridad consolidada

- autenticacion
- comercios
- usuarios internos del comercio
- productos
- proveedores
- compras
- ventas
- stock
- dashboard simple

### Fuera del flujo principal por ahora

- multi-sucursal compleja
- tenant switching
- memberships avanzados
- RBAC sofisticado
- tenancy modular avanzada
