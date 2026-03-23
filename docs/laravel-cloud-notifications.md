# Notificaciones en Laravel Cloud

## Checklist de despliegue

- Ejecutar migraciones antes de habilitar scheduler y workers.
- Mantener `CACHE_STORE=database` o cualquier store compartido para que `onOneServer()` funcione bien.
- Usar `MAIL_MAILER=failover_cloud` para tener fallback a logs si SMTP falla.
- Dejar `MAIL_VERIFY_PEER=true` en Cloud con certificados validos.
- Configurar un worker para procesar `NOTIFICATIONS_QUEUE=notifications`.

## Comportamiento esperado

- El scheduler corre cada minuto en Laravel Cloud.
- La tarea `notifications:send-operational-alerts` se evalua una vez por hora.
- El comando solo encola trabajos; el envio real sale por cola.
- Cada envio queda registrado en `business_notification_dispatches` con estado `queued`, `sent`, `partial` o `failed`.

## Variables recomendadas

```env
QUEUE_CONNECTION=database
NOTIFICATIONS_QUEUE=notifications
MAIL_MAILER=failover_cloud
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=587
MAIL_USERNAME=notificaciones@comerstock.com
MAIL_PASSWORD=tu_clave
MAIL_VERIFY_PEER=true
MAIL_AUTO_TLS=true
MAIL_FROM_ADDRESS=notificaciones@comerstock.com
MAIL_FROM_NAME="ComerStock"
```

## Worker recomendado

- Si usas un solo worker, haz que escuche la cola `notifications`.
- Si prefieres simplicidad absoluta, puedes definir `NOTIFICATIONS_QUEUE=default` y reutilizar el worker general.
