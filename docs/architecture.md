# Arquitectura por dominios (Core + Stock + Appointments)

## Estado actual de la evolución

La aplicación se mantiene como **single-app** con una sola base compartida y aislamiento por `business_id`.

## Dominios

- **Core**: autenticación, contexto de negocio, dashboard general, usuarios y notificaciones.
- **Stock**: mantiene rutas actuales (`/products`, `/sales`, etc.) y suma namespace funcional bajo `/stock/...`.
- **Appointments**: nuevo dominio de turnos bajo `/appointments/...`.

## Feature flags por negocio

Se utiliza `business_features` con middleware `feature:{name}`.

Features incorporadas:
- `stock`
- `appointments`
- `pos`
- `reports`
- `public_booking`

## Compatibilidad incremental

- Se conservaron rutas legacy de stock para no romper UX/tests.
- Se agregaron rutas nuevas prefijadas por dominio para facilitar migración progresiva.
- `stock` se considera habilitado por defecto cuando no existe fila de feature (compatibilidad histórica).

## Siguiente etapa sugerida

- Migrar progresivamente los controladores y requests legacy a namespaces `Core/Stock/SuperAdmin`.
- Completar UI de edición en línea para Staff/Customers/Blocked slots/Calendar.
- Añadir permisos finos por rol para Appointments.
