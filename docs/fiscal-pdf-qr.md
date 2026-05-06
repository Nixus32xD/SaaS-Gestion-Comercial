# PDF fiscal y QR ARCA

## QR

El QR se genera en `App\Services\Fiscal\FiscalQrService` con la URL oficial:

`https://www.arca.gob.ar/fe/qr/?p=`

El parametro `p` es un JSON version 1 codificado con `base64_encode`. Los datos salen del `SaleFiscalDocument` autorizado, su `Sale`, `Business` y el `fiscal_payload` guardado al emitir.

Campos usados:

- `fecha`: `voucher_date` del payload fiscal, o fecha de autorizacion/venta.
- `cuit`: `business.fiscal_cuit`.
- `ptoVta`: `sale_fiscal_documents.fiscal_point_of_sale`.
- `tipoCmp`: `sale_fiscal_documents.fiscal_cbte_type`.
- `nroCmp`: `sale_fiscal_documents.fiscal_number`.
- `importe`: `sales.total`.
- `moneda` y `ctz`: payload fiscal o defaults de `config/fiscal.php`.
- `tipoDocRec` y `nroDocRec`: `fiscal_payload.customer`, soportando el formato nuevo (`document_type`/`document_number`) y el legacy (`doc_type`/`doc_number`), con Consumidor Final `99/0` por defecto.
- `tipoCodAut`: `CAE => E`, `CAEA => A`.
- `codAut`: `authorization_code` o `fiscal_cae`.

## PDF

El PDF se genera en `App\Services\Fiscal\FiscalPdfService` usando:

- `barryvdh/laravel-dompdf`
- `endroid/qr-code`

La vista imprimible esta en `resources/views/pdf/fiscal-document.blade.php`.

## Descarga

Ruta web autenticada:

`GET /sales/{sale}/fiscal-documents/{saleFiscalDocument}/pdf`

Nombre de ruta:

`sales.fiscal-documents.pdf`

Solo descarga si:

- El comprobante pertenece al comercio actual.
- El comprobante pertenece a la venta indicada.
- El modulo fiscal esta habilitado.
- El comprobante esta autorizado.
- Existen CUIT, punto de venta, tipo, numero, importe y codigo CAE/CAEA.

## Checklist produccion

- Confirmar `fiscal_cuit` real en cada comercio.
- Confirmar punto de venta y tipo de comprobante configurados por comercio.
- Validar que CAE/CAEA y vencimiento llegan correctamente desde la API fiscal.
- Probar lectura del QR con un comprobante autorizado real.
- Verificar DomPDF en el servidor con extension `gd` habilitada.
- Mantener `FISCAL_ENVIRONMENT=production` solo en produccion.
