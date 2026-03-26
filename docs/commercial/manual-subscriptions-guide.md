# Control Manual de Abonos

## Objetivo

Este modulo sirve para ordenar los cobros de cada comercio sin usar pasarela de pago automatica.
La idea es simple:

- definir que plan inicial compro el cliente,
- definir que mantenimiento mensual tiene,
- registrar manualmente cada pago,
- saber quien esta al dia, por vencer, en gracia o suspendido.

## Que se guarda por comercio

En cada comercio ahora podes cargar:

- plan de implementacion inicial: `Express`, `Esencial` o `Plus`,
- monto de implementacion pactado,
- plan de mantenimiento mensual: `Basico`, `Operativo` o `Prioritario`,
- monto mensual pactado,
- fecha de inicio del mantenimiento,
- fecha de vencimiento,
- dias de gracia,
- notas internas,
- historial manual de pagos.

## Como usarlo

### 1. Crear el comercio

Primero crea el comercio como siempre desde superadmin.

### 2. Configurar planes y abonos

En la edicion del comercio vas a ver la seccion `Planes y abonos`.

Carga:

- el plan inicial que contrató,
- el monto acordado,
- el plan mensual,
- el monto mensual real,
- la fecha de vencimiento,
- las notas que te sirvan para seguimiento.

Esto te deja el comercio ordenado aunque todavia no hayas registrado pagos.

### 3. Registrar pagos manuales

En la seccion `Registrar pago manual` elegi:

- si el pago fue de `Implementacion` o `Mantenimiento`,
- el plan relacionado,
- el monto pagado,
- la fecha del pago,
- y, si es mantenimiento, hasta que fecha queda cubierto.

Cada pago queda guardado en el historial.

Si el pago es de mantenimiento, la fecha `cubierto hasta` actualiza el vencimiento operativo del comercio.

## Estados del mantenimiento

### Al dia

El comercio tiene cobertura vigente y el vencimiento todavia no llego.

### Por vencer

El vencimiento esta cerca.
Sirve para anticiparte y recordarle al cliente el pago.
Ademas, si el recordatorio de mantenimiento esta activo, el sistema envia un mail automatico cuando faltan `7 dias` para el vencimiento.

### En gracia

El mantenimiento ya vencio, pero todavia esta dentro de la tolerancia configurada.
Por defecto la gracia es de `7 dias`.

### Suspendido

El comercio ya supero la gracia.
En ese estado el usuario del comercio no puede seguir operando hasta que registres un nuevo pago y renueves la cobertura.

## Regla actual de gracia

La implementacion actual usa esta logica:

- si vence el `30/04/2026`,
- la gracia corre hasta el `07/05/2026`,
- el `08/05/2026` ya queda suspendido.

## Recordatorio por mail

El comercio puede recibir un correo automatico cuando faltan `7 dias` para el vencimiento del mantenimiento.

Ese recordatorio:

- usa los mismos destinatarios configurados en `Notificaciones`,
- toma el plan y el importe mensual cargados en el comercio,
- funciona aunque las alertas operativas de stock o vencimientos esten pausadas,
- y no repite el mismo aviso para el mismo vencimiento.

## Que ve el cliente

Cuando el comercio esta por vencer o en gracia, dentro de la app aparece un aviso con:

- el plan de mantenimiento,
- el importe mensual pactado,
- la fecha de vencimiento,
- y, si corresponde, la fecha limite de gracia.

## Forma simple de explicarlo al cliente

Podes decirlo asi:

> Tu comercio tiene un plan de implementacion inicial y un mantenimiento mensual.  
> El mantenimiento se controla de forma manual y queda registrado en el sistema.  
> Cuando se acerca el vencimiento te figura el aviso dentro de la app.  
> Si se vence, tenes una gracia de 7 dias.  
> Pasada esa semana, el sistema se suspende hasta regularizar el pago.

## Recomendacion operativa

Para trabajar ordenado:

- carga el comercio apenas cierre,
- deja asentado el plan y el precio real aunque sea promocional,
- registra cada pago el mismo dia que entra,
- y revisa seguido el listado de comercios en superadmin para ver `por vencer`, `en gracia` y `suspendidos`.
