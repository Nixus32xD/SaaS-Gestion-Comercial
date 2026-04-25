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
FISCAL_API_STATUS_TIMEOUT=5
FISCAL_DEFAULT_POINT_OF_SALE=2
FISCAL_DEFAULT_DOCUMENT_TYPE=invoice_c
FISCAL_DEFAULT_CBTE_TYPE=11
FISCAL_DEFAULT_CONCEPT=1
FISCAL_ACTIVITIES=
```

`FISCAL_API_BASE_URL` queda apuntando a localhost mientras la API fiscal corre localmente. Cuando se publique en Laravel Cloud, solo hay que cambiar esa URL y limpiar cache de configuracion si corresponde.

## Configuracion por comercio

Desde el panel de superadmin, cada comercio puede definir:

- facturacion electronica habilitada;
- ID externo del comercio usado por la API fiscal;
- CUIT fiscal del contribuyente emisor;
- punto de venta;
- tipo interno de documento;
- tipo de comprobante ARCA;
- concepto;
- actividades fiscales.

Si el ID externo queda vacio, el SaaS envia el `business_id` interno como fallback.
El CUIT fiscal se guarda normalizado con 11 digitos y se usa como referencia visual del setup ARCA y como metadata del CSR. No reemplaza al ID externo que identifica al comercio dentro de la API fiscal.

Cuando `FISCAL_ENABLED=true` y se habilita facturacion electronica para un comercio, el guardado de funciones sincroniza la empresa fiscal en la API externa con `POST /api/fiscal/companies`. Si se cambia el ID externo de una empresa fiscal ya habilitada, primero intenta actualizar la company anterior con `PUT /api/fiscal/companies/{company}` y, si no existe, crea la nueva. Para la API externa, cualquier ambiente distinto de `production` se envia como `testing`.

## Onboarding de certificado

El SaaS no genera ni guarda claves privadas. Para configurar una credencial fiscal:

1. el administrador del comercio verifica el CUIT fiscal configurado y solicita un CSR desde `/electronic-billing`;
2. la API fiscal externa genera o reutiliza la `.key`, persiste la key en su almacenamiento seguro y devuelve el CSR;
3. el SaaS guarda solo metadatos locales de onboarding, el ID de credencial, el nombre visible de la key, el estado y el CSR;
4. el administrador ingresa con clave fiscal al CUIT correspondiente, sube el CSR en ARCA/AFIP y descarga el certificado `.crt`;
5. el administrador pega o sube el `.crt` en el SaaS;
6. el SaaS envia el certificado a la API fiscal externa para validar que matchee con la key generada;
7. si la API confirma la validacion, la credencial local queda activa y se puede ejecutar el test de credenciales.

Errores como `certificate_private_key_mismatch`, `certificate_expired` o `private_key_invalid` se muestran como errores de onboarding, sin almacenar el certificado completo en el SaaS.

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

La venta se registra primero con el flujo existente de stock y totales. No se emite automaticamente.

Desde el detalle de venta se puede emitir el comprobante fiscal. El backend arma el payload y llama a:

- `POST /api/fiscal/documents`

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

## Payload

Para Factura C monotributo se envia:

- `document_type`: `invoice_c`;
- `cbte_type`: `11`;
- `amounts.imp_total`: total de venta;
- `amounts.imp_neto`: total de venta;
- IVA, tributos, exento y no gravado en cero;
- cliente por defecto consumidor final sin identificar;
- `items` como trazabilidad interna, aunque WSFEv1 no use detalle de items.

Para concepto servicios o productos+servicios, el SaaS envia `service_dates` usando la fecha de venta.
