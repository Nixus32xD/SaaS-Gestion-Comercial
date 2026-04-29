# Desvinculacion fiscal AFIP/ARCA

## Objetivo

El SaaS no debe integrarse directamente con AFIP/ARCA ni conocer detalles de credenciales fiscales, SOAP, WSAA o WSFE. La unica comunicacion fiscal activa queda centralizada por HTTP contra la API fiscal propia configurada con:

- `FISCAL_API_BASE_URL`
- `FISCAL_API_TOKEN`
- `FISCAL_API_TIMEOUT`
- `FISCAL_API_CONNECT_TIMEOUT`

## Flujo vigente

```text
Venta del SaaS
  -> FiscalSaleDocumentService
  -> FiscalApiClient
  -> API fiscal propia
  -> ARCA/AFIP
```

El SaaS conserva solo estado local de la venta fiscal: `fiscal_document_id`, estado, autorizacion devuelta por la API, payload enviado, response recibida y datos necesarios para conciliacion/reintento.

## Onboarding operativo por proxy

Como el operador puede tener acceso solo al SaaS, se habilito un flujo minimo de
onboarding que actua unicamente como proxy HTTP hacia la API fiscal propia:

```text
Admin del SaaS
  -> FiscalCredentialProxyController
  -> FiscalApiClient
  -> API fiscal propia
  -> ARCA/AFIP
```

Este flujo permite:

- pedir a la API fiscal que genere o recupere un CSR;
- mostrar/descargar el CSR devuelto por la API;
- subir el contenido del `.crt` para que la API lo valide y active.

El SaaS no genera claves privadas, no recibe `.key`, no guarda certificados y no
ejecuta test de credenciales. El `.crt` se valida y persiste solo en la API
fiscal. Las rutas estan restringidas a `business.admin`.

## Archivos eliminados o desactivados

El flujo legacy de onboarding con almacenamiento local de credenciales fiscales
fue retirado del SaaS:

- `app/Http/Controllers/Fiscal/FiscalCredentialOnboardingController.php`
- `app/Http/Requests/Fiscal/GenerateFiscalCredentialCsrRequest.php`
- `app/Http/Requests/Fiscal/UploadFiscalCertificateRequest.php`
- `app/Services/Fiscal/FiscalCredentialOnboardingService.php`
- `app/Models/BusinessFiscalCredential.php`
- `tests/Feature/Fiscal/FiscalCredentialOnboardingTest.php`

Estos nombres de ruta se reintrodujeron solo como proxy hacia la API fiscal:

- `electronic-billing.credentials.csr`
- `electronic-billing.credentials.certificate.store`

No se reintrodujo `electronic-billing.credentials.test`.

## Archivos modificados

- `app/Services/Fiscal/FiscalApiClient.php`: conserva solo endpoints HTTP contra la API fiscal propia. Incluye emision/consulta/conciliacion y proxy administrativo para CSR/carga de `.crt`; no contiene SOAP, WSAA, WSFE ni manejo de claves.
- `app/Http/Controllers/Fiscal/FiscalCredentialProxyController.php`: proxy admin para pedir CSR y cargar `.crt` en la API fiscal sin guardar credenciales en el SaaS.
- `app/Http/Requests/Fiscal/GenerateFiscalCredentialCsrProxyRequest.php` y `app/Http/Requests/Fiscal/UploadFiscalCredentialCertificateProxyRequest.php`: validaciones del proxy. La carga de certificado no flashea el contenido ante errores de validacion.
- `config/fiscal.php`: elimina el fallback `FISCAL_API_URL` y `FISCAL_API_STATUS_TIMEOUT`; el cliente usa solo base URL, token y timeouts principales.
- `.env.example` y `.env.testing`: se removieron variables fiscales obsoletas.
- `app/Http/Controllers/Fiscal/ElectronicBillingController.php`: consume estado normalizado de API fiscal y expone props de onboarding proxy solo para admins.
- `resources/js/Pages/Fiscal/Index.vue`: muestra dashboard fiscal y, para admins, formularios proxy de CSR/CRT sin `.key` ni test de credenciales.
- `app/Models/Business.php`: se retiro la relacion `fiscalCredentials`.
- `app/Services/Fiscal/FiscalApiErrorMapper.php`: los mensajes quedan en terminos de API fiscal, sin detalles internos de proveedor fiscal.
- `app/Services/Fiscal/FiscalPointOfSaleOptionsService.php`: espera payload normalizado de la API fiscal y deja de parsear estructuras propias del proveedor fiscal.
- `resources/js/Pages/Admin/Businesses/Edit.vue` y `resources/js/Pages/Sales/Show.vue`: textos fiscales actualizados para no exponer detalles internos del proveedor fiscal.
- `database/migrations/2026_04_29_000100_add_fiscal_environment_to_businesses_table.php`: agrega ambiente fiscal explicito por comercio (`testing` o `production`).
- Tests de facturacion electronica, ventas fiscales y configuracion de comercios actualizados al flujo desacoplado.

## Compatibilidad historica

La migracion `database/migrations/2026_04_24_000300_create_business_fiscal_credentials_table.php` queda solo por compatibilidad historica. El codigo activo ya no usa esa tabla y no existe modelo, ruta, servicio ni boton que escriba o lea credenciales desde el SaaS.

La tabla historica `business_fiscal_credentials` podria existir en bases ya migradas. No debe usarse para nuevas funcionalidades.

## Limpieza futura recomendada

En una limpieza de base de datos planificada, evaluar:

- Crear una migracion para eliminar `business_fiscal_credentials` si no hay datos que auditar.
- Purgar registros historicos de esa tabla antes de eliminarla, si contienen CSR heredados.
- Evaluar si los campos legacy `fiscal_cae` y `fiscal_cae_expires_at` pueden consolidarse en `authorization_code` y `authorization_expires_at`. Por ahora se mantienen porque el SaaS puede guardar autorizaciones devueltas por la API fiscal.

## Verificacion manual

1. Configurar `FISCAL_ENABLED=true`, `FISCAL_API_BASE_URL` y `FISCAL_API_TOKEN`.
2. Entrar como superadmin a un comercio y habilitar facturacion electronica.
3. Confirmar que al guardar configuracion fiscal solo se llama a `POST/PUT /fiscal/companies`.
4. Entrar a `Facturacion electronica` y confirmar que solo se consultan:
   - `GET /fiscal/companies/{company}/status`
   - `GET /fiscal/companies/{company}/activities`
   - `GET /fiscal/companies/{company}/points-of-sale`
5. Como admin del comercio, generar CSR y confirmar que el SaaS llama solo a:
   - `POST /fiscal/companies/{company}/credentials/csr`
6. Cargar un `.crt` y confirmar que el SaaS llama solo a:
   - `PUT /fiscal/companies/{company}/credentials/{credential}/certificate`
7. Confirmar que el SaaS no guarda `.key`, `private_key` ni certificado en tablas locales.
8. Emitir una venta fiscal y confirmar que la llamada sale a `POST /fiscal/documents`.
9. Forzar un estado incierto y confirmar que la conciliacion llama a:
   - `POST /fiscal/documents/{id}/reconcile`, o
   - `GET /fiscal/documents/by-origin` si aun no hay `fiscal_document_id`.
10. Revisar que no exista ruta ni boton de test de credenciales en el SaaS.
