# Despliegue independiente en Render

Este proyecto incluye un Blueprint en `render.yaml` que crea recursos nuevos y no modifica servicios existentes:

- Web Service: `barberia-nivel-club-shaka`
- PostgreSQL: `barberia-nivel-club-db`
- Región: Oregon
- Canal de WhatsApp: API oficial de Meta, desactivada hasta completar la conexión

## Flujo

1. Publica el repositorio privado en GitHub.
2. En Render abre **New > Blueprint** y selecciona ese repositorio.
3. Confirma que Render detecte exactamente un Web Service y una base PostgreSQL nuevos.
4. Completa las cuatro variables `DEMO_ADMIN_*` solicitadas por Render.
5. Para habilitar el botón **Conectar con Meta**, añade los valores globales `META_APP_ID`, `META_APP_SECRET`, `META_EMBEDDED_SIGNUP_CONFIGURATION_ID` y `META_WEBHOOK_VERIFY_TOKEN`. Son datos de la aplicación de Meta del operador, no del administrador de cada barbería.
6. Aplica el Blueprint y espera el estado **Live**.
7. Comprueba `/health`, el formulario de acceso y el panel.

El contenedor ejecuta migraciones antes de iniciar. La carga ficticia es idempotente: si la base ya contiene un negocio, no modifica ni duplica registros.

## Límites del plan gratuito

- La web puede suspenderse después de 15 minutos sin tráfico y tardar cerca de un minuto en reactivarse.
- La base PostgreSQL gratuita expira a los 30 días.
- Los archivos subidos al disco local, como un logo nuevo, no sobreviven reinicios o despliegues. Los datos relacionales sí permanecen en PostgreSQL hasta que expire la base.
- El correo queda en modo `log`. WhatsApp solo realiza envíos externos cuando la conexión supera las comprobaciones de Meta, el webhook recibe un evento real y un administrador lo activa explícitamente.

Para convertirla en producción real se deben contratar recursos persistentes, completar la configuración y revisión de la aplicación en Meta, reemplazar los datos ficticios y completar los avisos de privacidad.
