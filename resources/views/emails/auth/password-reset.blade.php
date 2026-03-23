<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recupera tu acceso</title>
</head>
<body style="margin:0;padding:24px;background:#dbe4ef;font-family:Arial,sans-serif;color:#0f172a;">
    <div style="max-width:720px;margin:0 auto;background:#ffffff;border-radius:24px;overflow:hidden;box-shadow:0 20px 50px rgba(8,47,73,0.16);">
        <div style="padding:36px 28px;background:radial-gradient(circle at 85% 10%,rgba(103,232,249,0.26),transparent 30%),linear-gradient(120deg,#05264e 0%,#0f172a 48%,#101f4d 100%);color:#f8fafc;">
            <p style="margin:0 0 12px;font-size:12px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:#a5f3fc;">ComerStock</p>
            <h1 style="margin:0;font-size:30px;line-height:1.2;">Recupera tu acceso</h1>
            <p style="margin:14px 0 0;font-size:15px;line-height:1.7;color:#dbeafe;">
                Restablece tu contrasena y vuelve a gestionar tu comercio sin perder el ritmo de trabajo.
            </p>
        </div>

        <div style="padding:28px;">
            <p style="margin:0 0 16px;font-size:15px;line-height:1.7;">
                Hola {{ $recipientName !== '' ? e($recipientName) : 'equipo' }}, recibimos una solicitud para cambiar la contrasena de la cuenta <strong>{{ e($recipientEmail) }}</strong>.
            </p>

            <div style="border:1px solid #cbd5e1;border-radius:18px;background:#f8fafc;padding:18px;">
                <p style="margin:0 0 8px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#475569;">Que puedes hacer desde ComerStock</p>
                <p style="margin:0;font-size:14px;line-height:1.7;color:#334155;">
                    Recuperar tu acceso para seguir operando ventas, compras, stock, vencimientos y notificaciones desde tu panel.
                </p>
            </div>

            <div style="margin-top:18px;border:1px solid #bfdbfe;border-radius:18px;background:linear-gradient(180deg,#eff6ff 0%,#dbeafe 100%);padding:18px;">
                <p style="margin:0 0 10px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#0f4c81;">Accion recomendada</p>
                <p style="margin:0 0 16px;font-size:15px;line-height:1.7;color:#0f172a;">
                    Este enlace estara disponible durante <strong>{{ $expirationMinutes }} minutos</strong>. Si fuiste tu, restablece la contrasena desde el boton siguiente.
                </p>
                <a
                    href="{{ $resetUrl }}"
                    style="display:inline-block;border-radius:999px;background:#22d3ee;padding:14px 24px;font-size:14px;font-weight:700;letter-spacing:0.01em;color:#082f49;text-decoration:none;"
                >
                    Restablecer contrasena
                </a>
            </div>

            <div style="margin-top:18px;border:1px solid #dbeafe;border-radius:18px;background:#ffffff;padding:18px;">
                <p style="margin:0 0 8px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#475569;">Seguridad</p>
                <p style="margin:0 0 8px;font-size:14px;line-height:1.7;color:#334155;">
                    Si no solicitaste este cambio, puedes ignorar este correo y tu contrasena seguira igual.
                </p>
                <p style="margin:0;font-size:14px;line-height:1.7;color:#334155;">
                    Tambien puedes volver al acceso principal desde <a href="{{ $loginUrl }}" style="color:#0f4c81;text-decoration:underline;">{{ $loginUrl }}</a>.
                </p>
            </div>

            <div style="margin-top:20px;padding-top:18px;border-top:1px solid #e2e8f0;">
                <p style="margin:0 0 8px;font-size:13px;line-height:1.6;color:#64748b;">
                    Si el boton no funciona, copia y pega este enlace en tu navegador:
                </p>
                <p style="margin:0;font-size:13px;line-height:1.7;word-break:break-word;">
                    <a href="{{ $resetUrl }}" style="color:#0f4c81;text-decoration:underline;">{{ $resetUrl }}</a>
                </p>
            </div>
        </div>

        <div style="padding:18px 28px;background:#f8fafc;border-top:1px solid #e2e8f0;">
            <p style="margin:0;font-size:12px;line-height:1.7;color:#64748b;">
                ComerStock · {{ e($supportLabel) }}
            </p>
        </div>
    </div>
</body>
</html>
