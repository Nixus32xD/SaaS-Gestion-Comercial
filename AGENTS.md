# AGENTS.md

## Contexto del proyecto
Este proyecto es un SaaS web hecho con Laravel + Vue + Inertia + Tailwind.

## Decisión arquitectónica actual
El producto debe consolidarse como un MVP business-centric simple.
No usar multi-tenant avanzado en esta etapa.
La separación de datos se hace con `business_id` sobre una base compartida.

## Reglas obligatorias
- Toda entidad operativa del comercio debe depender de `business_id`.
- Toda query de datos del negocio debe filtrar por `business_id`.
- No mantener tenancy avanzada en paralelo.
- No crear abstracciones innecesarias.
- Priorizar claridad, mantenibilidad y estabilidad.

## Backend
- Seguir convenciones de Laravel.
- Preferir Services/Actions si evitan controladores demasiado cargados.
- Usar transacciones en compras, ventas y movimientos de stock.
- Agregar índices y constraints compuestos por `business_id` cuando corresponda.
- No duplicar lógica de stock, compras o ventas.

## Frontend
- Mantener consistencia entre Laravel, Inertia y Vue.
- No enviar métricas pesadas en shared data global.
- Preferir pantallas simples y funcionales para el MVP.

## Testing
- Mantener `php artisan test` funcionando.
- Agregar o corregir tests de aislamiento por `business_id`.
- Agregar tests básicos de compras, ventas y stock.

## Prioridad funcional
1. productos
2. categorías
3. stock
4. compras
5. ventas
6. caja básica
7. clientes
8. proveedores
9. usuarios del comercio
10. dashboard simple