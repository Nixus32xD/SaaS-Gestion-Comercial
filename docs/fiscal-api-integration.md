# Integracion con API fiscal externa

## Alcance

El SaaS no guarda certificados, claves privadas ni tokens WSAA de ARCA/AFIP. La emision fiscal se delega a una API externa Laravel, y este proyecto solo persiste el estado fiscal asociado a cada venta.

## Configuracion

Variables del SaaS:

```env
FISCAL_ENABLED=false
FISCAL_API_BASE_URL=http://127.0.0.1:8000/api
FISCAL_API_TOKEN=un-token-largo-seguro
FISCAL_API_TIMEOUT=60
FISCAL_API_CONNECT_TIMEOUT=3
FISCAL_DEFAULT_POINT_OF_SALE=2
FISCAL_DEFAULT_CONDITION=monotributo
FISCAL_DEFAULT_DOCUMENT_TYPE=invoice_c
FISCAL_DEFAULT_CBTE_TYPE=11
FISCAL_DEFAULT_CONCEPT=1
FISCAL_DEFAULT_AUTHORIZATION_MODE=cae
FISCAL_ACTIVITIES=
```

`FISCAL_API_BASE_URL` queda apuntando a localhost mientras la API fiscal corre localmente. Cuando se publique en Laravel Cloud, solo hay que cambiar esa URL y limpiar cache de configuracion si corresponde.

## Configuracion por comercio

Desde el panel de superadmin, cada comercio puede definir:

- facturacion electronica habilitada;
- ID externo del comercio usado por la API fiscal;
- CUIT fiscal del contribuyente emisor;
- condicion fiscal del comercio: `monotributo`, `responsable_inscripto` o `exento`;
- punto de venta;
- tipo interno de documento legacy/admin;
- tipo de comprobante ARCA legacy/admin;
- concepto;
- modo de autorizacion fiscal: `cae`, `caea` o `auto`;
- actividades fiscales.

Si el ID externo queda vacio, el SaaS envia el `business_id` interno como fallback.
El CUIT fiscal se guarda normalizado con 11 digitos y se usa como referencia visual del setup fiscal. No reemplaza al ID externo que identifica al comercio dentro de la API fiscal.

Cuando `FISCAL_ENABLED=true` y se habilita facturacion electronica para un comercio, el guardado de funciones sincroniza la empresa fiscal en la API externa con `POST /api/fiscal/companies`. Si se cambia el ID externo de una empresa fiscal ya habilitada, primero intenta actualizar la company anterior con `PUT /api/fiscal/companies/{company}` y, si no existe, crea la nueva. Para la API externa, cualquier ambiente distinto de `production` se envia como `testing`.

## Onboarding de certificado

El SaaS no genera ni guarda claves privadas. Para configurar una credencial fiscal:

1. el administrador del comercio verifica el CUIT fiscal configurado y solicita un CSR desde `/electronic-billing`;
2. la API fiscal externa genera o reutiliza la `.key`, persiste la key en su almacenamiento seguro y devuelve el CSR;
3. el SaaS muestra el ID de credencial, el nombre visible de la key, el estado y el CSR devueltos por la API, sin persistirlos como credencial local;
4. el administrador ingresa con clave fiscal al CUIT correspondiente, sube el CSR en ARCA/AFIP y descarga el certificado `.crt`;
5. el administrador pega o sube el `.crt` en el SaaS;
6. el SaaS envia el certificado a la API fiscal externa para validar que matchee con la key generada;
7. si la API confirma la validacion, la credencial queda activa en la API fiscal.

El SaaS no tiene endpoint de test de credenciales. Errores como `certificate_private_key_mismatch`, `certificate_expired` o `private_key_invalid` se muestran como errores de onboarding informados por la API, sin almacenar el certificado en el SaaS.

## Modulo opcional

El modulo se muestra solo cuando:

- `FISCAL_ENABLED=true`;
- el comercio tiene habilitada la facturacion electronica en sus funciones.

Cuando el modulo esta desactivado no aparece en la barra lateral, la ruta interna queda bloqueada y las acciones de emision/conciliacion responden `403` antes de llamar a la API fiscal.

La pantalla interna del modulo es `/electronic-billing` y expone:

- configuracion fiscal basica del comercio;
- estado de conexion con la API fiscal;
- estado de certificado/setup informado por la API fiscal;
- actividades y puntos de venta devueltos por la API;
- ultimos comprobantes emitidos;
- acciones de conciliacion o reintento cuando el estado local lo permite.

## Flujo de venta

La venta se registra primero con el flujo existente de stock y totales. Si la
facturacion electronica esta habilitada para el comercio, el SaaS intenta emitir
automaticamente el comprobante fiscal al terminar de registrar la venta.

Si la API autoriza, el detalle de venta queda con CAE/CAEA, numero y vencimiento.
Si la API rechaza, falla o deja el estado incierto, la venta queda registrada y
el intento fiscal queda guardado para resolverlo desde el detalle de la venta.

Desde el cierre de venta o desde el detalle de venta, el backend arma el payload
y llama a:

- `POST /api/fiscal/documents`

El payload normal de venta incluye `invoice_mode=auto`. El SaaS no calcula ni
fuerza Factura A/B/C; la API fiscal resuelve el `cbte_type` con la condicion
fiscal del comercio y la condicion IVA del receptor.

Para estados inciertos o en proceso, no se reintenta ciegamente. Se usa conciliacion:

- `POST /api/fiscal/documents/{id}/reconcile`, si existe ID fiscal externo;
- `GET /api/fiscal/documents/by-origin?business_id=...&origin_type=sale&origin_id=...`, si no hay ID externo local.

## Estados

Estados persistidos en `sale_fiscal_documents`:

- `authorized`: autorizado con CAE, numero y vencimiento.
- `rejected`: rechazo fiscal, no se reintenta automaticamente.
- `error`: error local o respuesta fallida de la API fiscal.
- `uncertain`: timeout o resultado no confirmado; requiere conciliacion.
- `processing`: intento creado localmente y pendiente de respuesta.

La autorizacion se guarda en campos genericos para soportar CAE y CAEA sin romper el historico:

- `authorization_type`: `CAE` o `CAEA`;
- `authorization_code`: codigo de autorizacion;
- `authorization_expires_at`: vencimiento;
- `caea_period`, `caea_order`, `caea_report_status`, `caea_reported_at`: datos especificos de CAEA.

Los campos legacy `fiscal_cae` y `fiscal_cae_expires_at` se mantienen para compatibilidad y se completan cuando la autorizacion es CAE.

## Idempotencia

La primera clave por venta es deterministica:

```text
sale:{business_id}:{sale_id}:invoice
```

Si un comprobante ya esta `authorized`, la venta no puede emitirse otra vez.

Si el ultimo intento quedo `rejected` o `error`, la accion manual de emitir crea un intento nuevo con clave:

```text
sale:{business_id}:{sale_id}:invoice:retry:{attempt_number}
```

Si el ultimo intento quedo `uncertain` o `processing`, primero se debe conciliar.

Los errores `502`, `504`, duplicados o problemas de numeracion no habilitan reintento directo: el estado queda `uncertain` y el usuario debe conciliar para verificar si ARCA ya proceso el comprobante.

## Errores visibles para el usuario

El SaaS mapea localmente los errores de la API fiscal con `FiscalApiErrorMapper`. La respuesta de apiArca puede incluir `code`, `message`, `technical_message`, `status`, `retryable`, `requires_reconcile` y `category`.

Categorias soportadas:

- `provider_infrastructure`;
- `timeout`;
- `authentication`;
- `validation`;
- `numbering`;
- `duplicated`;
- `unknown`.

Mensajes base:

- `502`: la API fiscal informo un error interno o de infraestructura. No se debe volver a emitir directamente. Usar Conciliar para verificar si el comprobante fue procesado.
- `504`: la API fiscal no respondio a tiempo. El estado del comprobante quedo incierto. Usar Conciliar antes de reintentar.
- autenticacion/configuracion: revisar token, CUIT y configuracion fiscal externa.
- validacion: revisar importes, IVA, documento del receptor, tipo de comprobante y punto de venta.

## Payload

Para ventas comunes se envia:

- `invoice_mode`: `auto`;
- `origin.type`: `sale`;
- `origin.id`: ID interno de venta;
- `amounts.imp_total`: total de venta;
- `amounts.imp_neto`: total de venta;
- IVA, tributos, exento y no gravado en cero;
- receptor por defecto consumidor final sin identificar;
- si el cliente pide factura con datos: nombre/razon social, CUIT/DNI, condicion IVA y domicilio;
- `items` como trazabilidad interna, aunque WSFEv1 no use detalle de items.

Para concepto servicios o productos+servicios, el SaaS envia `service_dates` usando la fecha de venta.

TODO: cuando productos/precios manejen alicuotas, agregar `iva_items` reales para emisores Responsable Inscripto. Hoy la API decide A/B/C, pero el SaaS todavia envia importes sin discriminacion de IVA.
