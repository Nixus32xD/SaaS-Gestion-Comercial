<!doctype html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 12px; }
        .header { border-bottom: 2px solid #111827; padding-bottom: 14px; margin-bottom: 18px; }
        .title { font-size: 22px; font-weight: bold; margin: 0; }
        .muted { color: #4b5563; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid td { vertical-align: top; width: 50%; }
        .box { border: 1px solid #d1d5db; padding: 10px; margin-bottom: 12px; }
        .box-title { font-weight: bold; font-size: 13px; margin-bottom: 6px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.items th, table.items td { border-bottom: 1px solid #e5e7eb; padding: 7px 5px; text-align: left; }
        table.items th { background: #f3f4f6; font-size: 11px; text-transform: uppercase; }
        .right { text-align: right; }
        .total { font-size: 16px; font-weight: bold; }
        .qr { width: 145px; height: 145px; }
        .footer { margin-top: 18px; padding-top: 12px; border-top: 1px solid #d1d5db; font-size: 11px; }
        .qr-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .qr-cell { width: 160px; vertical-align: top; }
        .qr-url-cell { vertical-align: top; padding-left: 12px; }
        .qr-url { font-size: 9px; line-height: 1.35; color: #4b5563; word-wrap: break-word; word-break: break-all; }
    </style>
</head>
<body>
    <div class="header">
        <table class="grid">
            <tr>
                <td>
                    <p class="title">{{ $business->name }}</p>
                    <div>CUIT: {{ $business->fiscal_cuit }}</div>
                    @if($business->address)
                        <div>Domicilio: {{ $business->address }}</div>
                    @endif
                    <div class="muted">Condicion IVA: {{ $business->fiscal_document_type ? strtoupper(str_replace('_', ' ', $business->fiscal_document_type)) : 'No informada' }}</div>
                </td>
                <td class="right">
                    <p class="title">{{ $voucherLabel }}</p>
                    <div>Punto de venta: {{ str_pad((string) $document->fiscal_point_of_sale, 5, '0', STR_PAD_LEFT) }}</div>
                    <div>Numero: {{ str_pad((string) $document->fiscal_number, 8, '0', STR_PAD_LEFT) }}</div>
                    <div>Fecha: {{ $sale->sold_at?->format('Y-m-d') ?? $document->authorized_at?->format('Y-m-d') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="box">
        <div class="box-title">Receptor</div>
        <div>Cliente: {{ $sale->customer?->name ?? data_get($document->fiscal_payload, 'customer.name', 'Consumidor Final') }}</div>
        @if($sale->customer?->address)
            <div>Domicilio: {{ $sale->customer->address }}</div>
        @endif
        <div>Documento: {{ data_get($document->fiscal_payload, 'customer.doc_type', 99) }} - {{ data_get($document->fiscal_payload, 'customer.doc_number', 0) }}</div>
    </div>

    <div class="box">
        <div class="box-title">Detalle</div>
        <table class="items">
            <thead>
                <tr>
                    <th>Descripcion</th>
                    <th class="right">Cantidad</th>
                    <th class="right">Precio</th>
                    <th class="right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sale->items as $item)
                    <tr>
                        <td>{{ $item->product_name }}</td>
                        <td class="right">{{ number_format((float) $item->quantity, 3, ',', '.') }}</td>
                        <td class="right">$ {{ number_format((float) $item->unit_price, 2, ',', '.') }}</td>
                        <td class="right">$ {{ number_format((float) $item->subtotal, 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">Sin items informados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <table class="grid">
        <tr>
            <td>
                <div class="box">
                    <div class="box-title">Autorizacion fiscal</div>
                    <div>{{ $authorizationLabel }}: {{ $document->authorization_code ?? $document->fiscal_cae }}</div>
                    @if($document->authorization_type === \App\Models\SaleFiscalDocument::AUTHORIZATION_CAE || $document->fiscal_cae_expires_at)
                        <div>Vencimiento CAE: {{ $document->authorization_expires_at?->format('Y-m-d') ?? $document->fiscal_cae_expires_at?->format('Y-m-d') }}</div>
                    @endif
                    <div class="muted">Comprobante autorizado por ARCA</div>
                </div>
            </td>
            <td class="right">
                <div class="box">
                    <div>Subtotal: $ {{ number_format((float) $sale->subtotal, 2, ',', '.') }}</div>
                    <div>Descuento: $ {{ number_format((float) $sale->discount, 2, ',', '.') }}</div>
                    <div class="total">Total: $ {{ number_format((float) $sale->total, 2, ',', '.') }}</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">
        <table class="qr-table">
            <tr>
                <td class="qr-cell">
                    <img class="qr" src="{{ $qrImage }}" alt="QR ARCA">
                </td>
                <td class="qr-url-cell">
                    <div class="box-title">QR ARCA</div>
                    <div class="qr-url">{!! nl2br(e(wordwrap((string) $qrUrl, 56, "\n", true))) !!}</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
