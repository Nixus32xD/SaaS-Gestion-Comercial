<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Prueba SMTP</title>
</head>
<body>
    <h1>Prueba SMTP de {{ $appName }}</h1>

    <p>Este correo confirma que la configuracion SMTP esta respondiendo.</p>

    <ul>
        <li>Remitente: {{ $fromName }} &lt;{{ $fromAddress }}&gt;</li>
        <li>Servidor: {{ $host }}:{{ $port }}</li>
        <li>Fecha: {{ $sentAt }}</li>
    </ul>

    <p>Si recibiste este mail, la salida de correo de la aplicacion ya puede usarse para alertas y recordatorios.</p>
</body>
</html>
