# Despliegue independiente en Render

Este proyecto incluye un Blueprint en `render.yaml` que crea recursos nuevos y no modifica servicios existentes:

- Web Service: `barberia-nivel-club-shaka`
- PostgreSQL: `barberia-nivel-club-db`
- Región: Oregon
- Canal de WhatsApp: entorno local, sin envíos externos

## Flujo

1. Publica el repositorio privado en GitHub.
2. En Render abre **New > Blueprint** y selecciona ese repositorio.
3. Confirma que Render detecte exactamente un Web Service y una base PostgreSQL nuevos.
4. Completa las cuatro variables `DEMO_ADMIN_*` solicitadas por Render.
5. Aplica el Blueprint y espera el estado **Live**.
6. Comprueba `/health`, el formulario de acceso y el panel.

El contenedor ejecuta migraciones antes de iniciar. La carga ficticia es idempotente: si la base ya contiene un negocio, no modifica ni duplica registros.

## Límites del plan gratuito

- La web puede suspenderse después de 15 minutos sin tráfico y tardar cerca de un minuto en reactivarse.
- La base PostgreSQL gratuita expira a los 30 días.
- Los archivos subidos al disco local, como un logo nuevo, no sobreviven reinicios o despliegues. Los datos relacionales sí permanecen en PostgreSQL hasta que expire la base.
- El correo queda en modo `log` y WhatsApp en modo local; no se realizan envíos externos.

Para convertirla en producción real se deben contratar recursos persistentes, configurar correo y Meta, reemplazar los datos ficticios y completar los avisos de privacidad.
