<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso inicial</title>
</head>
<body style="margin:0;padding:24px;background:#e2e8f0;font-family:Arial,sans-serif;color:#0f172a;">
    <div style="max-width:720px;margin:0 auto;background:#ffffff;border-radius:24px;overflow:hidden;">
        <div style="padding:32px 28px;background:linear-gradient(135deg,#082f49 0%,#0f172a 55%,#1e293b 100%);color:#f8fafc;">
            <p style="margin:0 0 12px;font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#a5f3fc;">ComerStock</p>
            <h1 style="margin:0;font-size:28px;line-height:1.25;">{{ $recipientIsAdmin ? 'Tu acceso ya esta listo' : 'El comercio ya fue creado' }}</h1>
            <p style="margin:14px 0 0;font-size:15px;line-height:1.7;color:#cbd5e1;">
                {{ $recipientIsAdmin ? 'Ya puedes ingresar y empezar a trabajar con tu comercio.' : 'Se configuro el comercio y el acceso administrador inicial.' }}
            </p>
        </div>

        <div style="padding:28px;">
            <p style="margin:0 0 16px;font-size:15px;line-height:1.7;">
                Hola {{ $recipientName !== '' ? e($recipientName) : 'equipo' }}, te compartimos los datos iniciales del comercio <strong>{{ e($businessName) }}</strong>.
            </p>

            <div style="border:1px solid #dbeafe;border-radius:18px;background:#f8fafc;padding:18px;">
                <p style="margin:0 0 8px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#475569;">Administrador creado</p>
                <p style="margin:0 0 6px;font-size:15px;"><strong>Nombre:</strong> {{ e($adminName) }}</p>
                <p style="margin:0 0 6px;font-size:15px;"><strong>Email:</strong> {{ e($adminEmail) }}</p>
                <p style="margin:0;font-size:15px;"><strong>Contrasena inicial:</strong> {{ e($plainPassword) }}</p>
            </div>

            <div style="margin-top:18px;border:1px solid #dbeafe;border-radius:18px;background:#f8fafc;padding:18px;">
                <p style="margin:0 0 10px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#475569;">Funciones actuales de la aplicacion</p>
                <ul style="margin:0;padding-left:18px;color:#0f172a;">
                    @foreach ($applicationFunctions as $feature)
                        <li style="margin:0 0 8px;font-size:14px;line-height:1.6;">{{ e($feature) }}</li>
                    @endforeach
                </ul>
            </div>

            <div style="margin-top:18px;border:1px solid #dbeafe;border-radius:18px;background:#f8fafc;padding:18px;">
                <p style="margin:0 0 10px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#475569;">Permisos del administrador</p>
                <ul style="margin:0;padding-left:18px;color:#0f172a;">
                    @foreach ($adminPermissions as $permission)
                        <li style="margin:0 0 8px;font-size:14px;line-height:1.6;">{{ e($permission) }}</li>
                    @endforeach
                </ul>
            </div>

            <div style="margin-top:18px;border:1px solid #bfdbfe;border-radius:18px;background:linear-gradient(180deg,#eff6ff 0%,#dbeafe 100%);padding:18px;">
                <p style="margin:0 0 8px;font-size:16px;font-weight:700;color:#0f172a;">Acceso</p>
                <p style="margin:0 0 8px;font-size:14px;line-height:1.6;color:#334155;">Ingresar al sistema: <a href="{{ $loginUrl }}" style="color:#0f4c81;">{{ $loginUrl }}</a></p>
                <p style="margin:0;font-size:14px;line-height:1.6;color:#334155;">Si quieres cambiar la contrasena o recuperarla mas adelante, puedes hacerlo desde: <a href="{{ $passwordResetUrl }}" style="color:#0f4c81;">{{ $passwordResetUrl }}</a></p>
            </div>
        </div>
    </div>
</body>
</html>
