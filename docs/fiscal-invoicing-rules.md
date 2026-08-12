# Reglas de facturacion fiscal

La API fiscal es la fuente de verdad para resolver Factura A/B/C. El SaaS no debe duplicar esa decision: envia `invoice_mode=auto`, los datos del comercio y los datos fiscales del receptor cuando el cliente los pide.

## Factura A/B/C

- Factura A: emisor Responsable Inscripto y receptor Responsable Inscripto.
- Factura B: emisor Responsable Inscripto y receptor no Responsable Inscripto.
- Factura C: emisor Monotributista o IVA Exento.

## Matriz emisor/receptor

| Emisor | Receptor | Comprobante |
| --- | --- | --- |
| Monotributista | Consumidor Final | Factura C |
| Monotributista | Responsable Inscripto | Factura C |
| Monotributista | Monotributista | Factura C |
| Monotributista | IVA Exento | Factura C |
| IVA Exento | Cualquier receptor soportado | Factura C |
| Responsable Inscripto | Responsable Inscripto | Factura A |
| Responsable Inscripto | Consumidor Final | Factura B |
| Responsable Inscripto | Monotributista | Factura B |
| Responsable Inscripto | IVA Exento | Factura B |

## Mapping ARCA/AFIP

| Dato | Codigo |
| --- | --- |
| Factura A | `cbte_type=1` |
| Factura B | `cbte_type=6` |
| Factura C | `cbte_type=11` |
| CUIT | `DocTipo=80` |
| DNI | `DocTipo=96` |
| Consumidor Final sin identificar | `DocTipo=99`, `DocNro=0` |

## Flujo recomendado

1. Venta registrada en el SaaS.
2. Pago aprobado o venta confirmada.
3. SaaS llama `POST /api/fiscal/documents` con `invoice_mode=auto`.
4. API fiscal resuelve el tipo de comprobante y solicita autorizacion.
5. SaaS guarda el resultado y concilia antes de reintentar estados inciertos.

El medio de pago no define el tipo de factura.

## UX de venta

- Sin datos fiscales del cliente: receptor `consumidor_final`.
- Si el cliente solicita factura con datos: cargar nombre/razon social, CUIT/DNI, condicion IVA y domicilio.
- El empleado no elige Factura A/B/C en venta comun.
- El producto mantiene tratamiento IVA y alicuota para que la venta pueda discriminar correctamente cuando el emisor es Responsable Inscripto.
- Los campos legacy de `document_type` y `cbte_type` quedan solo para configuracion tecnica/admin y compatibilidad.

## IVA en el payload

- Emisor monotributista o exento: se emite Factura C, no se discrimina IVA y el total viaja como importe neto del comprobante C.
- Emisor Responsable Inscripto: el SaaS separa el precio final en neto e IVA segun la alicuota del producto y envia `amounts.iva_items`.
- Si el producto es exento o no gravado, el importe se informa en `imp_op_ex` o `imp_tot_conc` para comprobantes A/B.
- El Libro IVA Ventas se consulta desde la API fiscal por mes, usando solo comprobantes autorizados.
