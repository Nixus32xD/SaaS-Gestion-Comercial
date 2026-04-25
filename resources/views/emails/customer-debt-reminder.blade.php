<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recordatorio de saldo pendiente</title>
</head>
<body style="margin:0;padding:24px;background:#e2e8f0;font-family:Arial,sans-serif;color:#0f172a;">
    <div style="max-width:720px;margin:0 auto;background:#ffffff;border-radius:24px;overflow:hidden;">
        <div style="padding:32px 28px;background:linear-gradient(135deg,#082f49 0%,#0f172a 55%,#1e293b 100%);color:#f8fafc;">
            <p style="margin:0 0 12px;font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#a5f3fc;">ComerStock</p>
            <h1 style="margin:0;font-size:28px;line-height:1.25;">Recordatorio de saldo pendiente</h1>
            <p style="margin:14px 0 0;font-size:15px;line-height:1.7;color:#cbd5e1;">
                Hola {{ e($customerName) }}, te escribimos de <strong>{{ e($businessName) }}</strong>.
            </p>
        </div>

        <div style="padding:28px;">
            <div style="border:1px solid #dbeafe;border-radius:18px;background:#f8fafc;padding:18px;">
                <p style="margin:0 0 8px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#475569;">Resumen actual</p>
                <p style="margin:0 0 6px;font-size:15px;"><strong>Saldo pendiente:</strong> {{ e($balanceLabel) }}</p>
                <p style="margin:0;font-size:15px;"><strong>Comprobantes pendientes:</strong> {{ $pendingSalesCount }}</p>
            </div>

            <div style="margin-top:18px;border:1px solid #bfdbfe;border-radius:18px;background:linear-gradient(180deg,#eff6ff 0%,#dbeafe 100%);padding:18px;">
                <p style="margin:0;font-size:15px;line-height:1.7;color:#334155;">
                    {{ e($reminderMessage) }}
                </p>
            </div>

            <p style="margin:18px 0 0;font-size:14px;line-height:1.7;color:#475569;">
                Si ya realizaste el pago recientemente, puedes responder este correo para que lo revisemos.
            </p>
        </div>
    </div>
</body>
</html>
