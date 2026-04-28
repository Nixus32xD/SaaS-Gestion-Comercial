# AGENTS.md

## Proyecto
Este proyecto es un SaaS de gestión comercial orientado a pequeños y medianos comercios de Argentina.
El sistema actualmente cubre o planea cubrir módulos como:

- gestión de productos
- stock
- ventas / POS
- compras
- proveedores
- clientes
- cuentas corrientes
- reportes
- configuración por comercio
- notificaciones
- funcionalidades exclusivas por comercio
- futura unificación con módulos de turnero o e-commerce

## Stack principal
- Laravel 12
- PHP 8.3+
- MySQL
- Inertia.js
- Vue 3
- Tailwind CSS
- Vite
- Arquitectura basada en backend fuerte con render de vistas por Inertia

## Reglas globales obligatorias
1. Antes de modificar código, revisar la estructura actual del proyecto y reutilizar lo existente.
2. No duplicar lógica si ya existe una clase, servicio, trait, helper o patrón parecido.
3. No introducir axios si los datos pueden resolverse correctamente mediante Inertia props, formularios o navegación estándar del proyecto.
4. Mantener consistencia con Laravel, Vue e Inertia ya implementados.
5. No romper funcionalidades actuales.
6. Si el cambio es grande, primero proponer plan de implementación por etapas.
7. Preferir cambios incrementales, seguros y testeables.
8. Respetar nombres de carpetas, convenciones del proyecto y estilo de código existente.
9. Siempre considerar impacto multi-comercio o por business_id.
10. Toda nueva funcionalidad debe contemplar permisos, validaciones, UX y mantenibilidad.

## Convenciones arquitectónicas
- La lógica de negocio importante no debe quedar incrustada en controladores o componentes Vue.
- Preferir Services / Actions / clases dedicadas para lógica compleja.
- Validaciones en Form Requests cuando aplique.
- Consultas complejas deben quedar claras y optimizadas.
- En frontend, dividir componentes grandes en subcomponentes reutilizables.
- Evitar componentes Vue gigantes y difíciles de mantener.
- Respetar el flujo server-driven con Inertia.

## Base de datos
- Toda migración debe ser reversible.
- Pensar índices cuando haya filtros, búsquedas o relaciones frecuentes.
- No asumir datos globales si en realidad pertenecen a un comercio.
- Si una tabla puede crecer mucho, considerarlo desde el diseño.

## UX y panel
- El sistema debe priorizar velocidad de uso en comercio real.
- Formularios claros.
- Tablas rápidas de leer.
- Acciones frecuentes visibles.
- Evitar exceso de pasos para ventas, stock o carga de productos.
- Todo lo que se use en mostrador debe optimizarse para agilidad.

## Seguridad
- Revisar autorización en cada módulo nuevo.
- No exponer datos de un comercio a otro.
- Validar correctamente entradas del usuario.
- Revisar edge cases de permisos y business context.

## Modo de trabajo esperado
Cuando se pida una tarea:
1. Revisar contexto y arquitectura actual.
2. Detectar archivos relevantes.
3. Proponer plan breve si el cambio es mediano o grande.
4. Implementar de forma consistente.
5. Explicar qué se cambió y qué faltaría validar.
6. Si aplica, sugerir tests o casos de prueba manual.

## Áreas especializadas
Este proyecto cuenta con agentes especializados por área.
Cada agente debe operar solo dentro de su responsabilidad principal y coordinarse con los demás a través de límites claros.

## Prioridades del proyecto
1. No romper producción ni lo ya usable
2. Mantener escalabilidad razonable
3. Resolver primero lo operativo del comercio
4. Mejorar UX real
5. Mantener código limpio y extensible
