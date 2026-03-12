# Documento Archivado
> Referencia historica. La arquitectura vigente esta en `docs/architecture/business-first-mvp.md`.

## Objetivo

Construir una base SaaS multi-tenant sólida para gestión comercial/POS, preparada para escalar por módulos (catálogo, stock, compras, ventas, caja, reportes y configuración), manteniendo aislamiento estricto por comercio (tenant).

## Estrategia de multi-tenant

- **Modelo:** base de datos compartida + columna `tenant_id` en entidades de negocio.
- **Aislamiento:** resolución de tenant en middleware por membresía del usuario (`tenant_user`).
- **Contexto activo:** `tenant_id` y `branch_id` en sesión + servicio `CurrentTenant`.
- **Escalabilidad futura:** este enfoque permite evolucionar a esquemas separados por tenant sin romper contratos de dominio.

## Dominios / módulos

1. **Auth**
   - Login, registro, recuperación, perfil.
   - Alta de tenant + usuario owner en onboarding.
2. **Tenancy**
   - Tenant, membresías de usuarios, contexto activo.
3. **Branches**
   - Sucursales, sucursal principal, contexto por sucursal.
4. **RBAC**
   - Roles, permisos, asignación por tenant y opcional por sucursal.
5. **Settings**
   - Configuración por tenant en formato clave/valor JSON.
6. **(Fases siguientes) Catalog, Inventory, Purchases, Sales, CashRegister, Customers, Suppliers, Reports**
   - Cada dominio agrega `tenant_id` y, cuando aplique, `branch_id`.

## Modelo de datos inicial (Fase 1)

- `tenants`
  - Datos del comercio, moneda, locale, estado y metadatos SaaS.
- `branches`
  - Sucursales por tenant, con bandera `is_main`.
- `tenant_user`
  - Membresía usuario-tenant, estado, owner, sucursal por defecto.
- `roles`
  - Roles por tenant (`owner`, `admin`, etc.) con posibilidad de personalización futura.
- `permissions`
  - Catálogo global de permisos por módulo.
- `permission_role`
  - Matriz de permisos por rol.
- `role_user`
  - Asignación de roles a usuarios por tenant y opcional por sucursal.
- `settings`
  - Parámetros por tenant en formato extensible.
- `users` (extendida)
  - Estado activo y última fecha de login.

## Lineamientos de implementación

- Controladores delgados.
- Validación en Form Requests.
- Lógica de onboarding en servicio transaccional.
- Relaciones Eloquent explícitas.
- Índices en claves de búsqueda y filtros críticos.
- Soft deletes en entidades administrativas (`tenants`, `branches`, `roles`).

## Roadmap por fases

1. **Fase 1 (actual)**
   - Arquitectura base, tenancy, sucursales, RBAC base, settings, onboarding.
2. **Fase 2**
   - Catálogo flexible: products, variants, barcodes, atributos dinámicos, stock base.
3. **Fase 3**
   - Proveedores y compras con impacto automático en stock.
4. **Fase 4**
   - Ventas POS con medios de pago y devoluciones base.
5. **Fase 5**
   - Caja: apertura/cierre/arqueo/movimientos.
6. **Fase 6**
   - Clientes y reportes operativos.
7. **Fase 7**
   - Configuración avanzada, permisos finos, auditoría y hardening.

## Decisiones clave

- Se prioriza consistencia y trazabilidad frente a atajos.
- El diseño favorece crecimiento modular sin acoplar dominios.
- La base deja preparado el terreno para suscripciones/planes y módulos premium.
