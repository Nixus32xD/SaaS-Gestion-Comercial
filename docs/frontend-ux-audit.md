# Auditoria UX Frontend

## Objetivo
Convertir el frontend actual en una interfaz de operacion comercial real:

- menos carga mental
- mas lectura rapida
- estados visibles
- acciones primarias obvias
- consistencia entre modulos

## Vistas relevadas

### Operacion diaria critica

- `Dashboard/Index.vue`
- `Products/Index.vue`
- `Products/Create.vue`
- `Products/Edit.vue`
- `Sales/Create.vue`
- `Sales/Index.vue`
- `Sales/Show.vue`
- `Purchases/Create.vue`
- `Purchases/Index.vue`
- `Purchases/Show.vue`
- `Customers/Index.vue`
- `Customers/Show.vue`
- `CustomerAccounts/Index.vue`

### Soporte operativo

- `Categories/Index.vue`
- `Categories/Create.vue`
- `Categories/Edit.vue`
- `Suppliers/Index.vue`
- `Suppliers/Create.vue`
- `Suppliers/Edit.vue`
- `Customers/Create.vue`
- `Customers/Edit.vue`
- `Notifications/Edit.vue`
- `Profile/Edit.vue`

### Infraestructura visual compartida

- `Layouts/AuthenticatedLayout.vue`
- `resources/css/app.css`
- componentes basicos heredados de Breeze

## Hallazgos por tipo de pantalla

### Dashboard

- Tiene datos utiles, pero no prioriza que revisar primero.
- Mezcla metricas, alertas y actividad reciente sin jerarquia fuerte.
- Faltaban CTA orientadas a accion inmediata sobre problemas.

### Productos

- `Index` ya tenia base funcional, pero faltaba visibilidad de estados.
- `Create` y `Edit` estaban muy planos: demasiados campos en una sola superficie.
- No era evidente si un producto quedaba listo para vender.
- Faltaban ayudas de margen, stock y completitud operativa.

### Clientes y cuenta corriente

- El saldo estaba visible, pero la accion siguiente no quedaba clara.
- Faltaba destacar rapidamente quien esta al dia, con saldo pendiente o con deuda alta.
- Las acciones de cobro y recordatorio competian con informacion secundaria.

### Ventas y compras

- La logica operativa es fuerte, pero las pantallas siguen cargadas.
- `Sales/Create` y `Purchases/Create` tienen buen flujo tecnico, pero necesitan simplificar lectura y resumen.
- Las listas requieren mas estado visual y filtros activos mas claros.

### Categorias, proveedores y perfil

- Son pantallas simples, pero todavia se sienten como CRUD basico.
- Necesitan alinearse al nuevo lenguaje visual y a la estructura por bloques.

### Notificaciones

- Ya tiene mejor estructura que otras vistas.
- Aun conviene unificar componentes y densidad visual con el resto del panel.

## Priorizacion

### Prioridad 1

- Dashboard
- Productos: index, create, edit
- Clientes: index, show
- Cuenta corriente: index

### Prioridad 2

- Sales/Create
- Purchases/Create
- Sales/Index
- Purchases/Index
- Sales/Show
- Purchases/Show

### Prioridad 3

- Categories/*
- Suppliers/*
- Customers/Create
- Customers/Edit
- Notifications/Edit
- Profile/Edit

## Estrategia de rediseño

### Base visual compartida

- introducir paneles reutilizables
- introducir metricas reutilizables
- introducir badges reutilizables
- unificar espaciados y secciones de formulario

### Reglas de mejora

- una pantalla debe responder primero que importa hoy
- los estados criticos deben leerse sin entrar al detalle
- las acciones primarias deben quedar visibles sin recorrer toda la vista
- formularios largos deben dividirse por tema
- los listados deben mostrar estados y contexto por fila

## Componentes base definidos

- `AppPanel.vue`
- `MetricCard.vue`
- `StatusBadge.vue`

## Mejoras implementadas en este turno

- nueva base compartida en `resources/css/app.css`
- nuevos componentes reutilizables para cards, metricas y estados
- rediseño operativo de:
  - `Dashboard/Index.vue`
  - `Products/Create.vue`
  - `Products/Edit.vue`
  - `Customers/Index.vue`
  - `Customers/Show.vue`
  - `CustomerAccounts/Index.vue`
  - `Sales/Create.vue`
  - `Sales/Index.vue`
  - `Sales/Show.vue`
  - `Purchases/Create.vue`
  - `Purchases/Index.vue`
  - `Purchases/Show.vue`
  - `Customers/Create.vue`
  - `Customers/Edit.vue`
  - `Categories/Index.vue`
  - `Categories/Create.vue`
  - `Categories/Edit.vue`
  - `Suppliers/Index.vue`
  - `Suppliers/Create.vue`
  - `Suppliers/Edit.vue`
  - `Notifications/Edit.vue`
  - `Profile/Edit.vue`

## Siguiente etapa recomendada

Actualizado despues del segundo pase:

- revisar modales y formularios parciales de perfil para seguir unificando densidad y labels
- reforzar estados operativos secundarios donde aun haya texto plano en vez de badges
- extender persistencia de borradores a otros flujos si aparecen mas puntos sensibles de perdida de trabajo

- rediseñar `Sales/Create.vue` y `Purchases/Create.vue` con foco en:
  - captura mas rapida
  - resumen sticky
  - validaciones mas visibles
  - acciones primarias mas cortas
- luego normalizar ventas/compras listados y pantallas simples
