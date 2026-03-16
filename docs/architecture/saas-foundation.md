# Arquitectura Operativa Actual
> Referencia vigente del modelo actual del proyecto.

## Objetivo

Documentar la base activa: una sola aplicacion, una sola base compartida y aislamiento estricto de datos por `business_id`.

## Decisiones activas

- El comercio vive en `businesses`.
- Los usuarios operativos usan `users.business_id`.
- No existe cambio de contexto entre multiples comercios para el mismo usuario operativo.
- Las entidades del negocio se relacionan por `business_id` y, cuando corresponde, por constraints compuestas.

## Tablas principales

- `businesses`
- `users`
- `suppliers`
- `categories`
- `products`
- `sales`
- `sale_items`
- `purchases`
- `purchase_items`
- `stock_movements`
- `business_document_sequences`

## Criterios de diseno

- Queries operativas siempre filtradas por `business_id`.
- Constraints compuestas para reforzar integridad entre tablas del mismo negocio.
- Servicios transaccionales para compras, ventas y movimientos de stock.
- Pantallas simples y mantenibles para el MVP.

## Alcance actual

- productos
- categorias
- stock
- compras
- ventas
- proveedores
- usuarios del comercio
- dashboard simple
