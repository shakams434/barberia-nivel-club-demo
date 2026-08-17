# Runbook operativo

## Diagnóstico rápido

```bash
php artisan about
php artisan migrate:status
php artisan schedule:list
php artisan queue:failed
php artisan queue:monitor database:default,database:messages,database:campaigns --max=100
```

Revisa `storage/logs/laravel.log` sin copiar tokens ni teléfonos completos a tickets.

## Mensajes fallidos

1. Filtra `failed` en Mensajes.
2. Revisa código y explicación.
3. Comprueba health check de WhatsApp.
4. Corrige token, plantilla o conectividad.
5. Reintenta desde la UI una sola vez o:

```bash
php artisan queue:retry ID
php artisan queue:work database --stop-when-empty --tries=3
```

No reintentes campañas indiscriminadamente: verifica consentimiento y frecuencia.

## Webhook caído

1. Comprueba `https://DOMINIO/up`.
2. Comprueba `https://DOMINIO/api/webhooks/whatsapp` con el flujo GET de Meta.
3. Verifica SSL, DNS, firewall, App Secret y verify token.
4. Confirma que Meta mantiene la suscripción `messages`.
5. Revisa eventos recientes en Meta.
6. No desactives la validación de firma para “resolver” el incidente.

## Token vencido o revocado

1. En **WhatsApp → Conexión**, pulsa **Pausar envíos reales**.
2. Genera/rota el token en Meta con el proceso autorizado.
3. Actualízalo desde **WhatsApp → Conexión**; no lo copies en `.env`.
4. Pulsa **Volver a comprobar credenciales**.
5. Envía un mensaje al número y confirma que llegue a Conversaciones.
6. Activa WhatsApp y procesa la cola pendiente.
7. Revoca el token anterior.

## Plantilla rechazada

1. Lee el motivo sincronizado.
2. Confirma categoría, idioma, variables, muestras y pie.
3. Separa promociones de Utility.
4. Crea una revisión del borrador; no modifiques campañas ya confirmadas.
5. Reenvía y espera el resultado de Meta.
6. No prometas aprobación.

## Cola detenida

1. Comprueba que hay jobs:

```bash
php artisan queue:failed
php artisan queue:work database --stop-when-empty --tries=3 --max-time=240 -v
```

2. Revisa permisos de `storage` y conexión DB.
3. Verifica que Cron llama a `schedule:run`.
4. Corrige el job que falla repetidamente antes de `queue:retry all`.

## Cron no se ejecuta

1. En cPanel verifica ruta absoluta de PHP y aplicación.
2. Ejecuta manualmente el comando exacto.
3. Revisa el archivo de salida del Cron.
4. Comprueba `php artisan schedule:list`.
5. Confirma que la ejecución anterior no quedó bloqueada.

## Restaurar backup

1. Activa mantenimiento.
2. Guarda una copia del estado roto.
3. Restaura archivos, `.env` y base compatibles entre sí.
4. Ejecuta `composer install --no-dev`, limpia caches y recrea storage link.
5. Valida migraciones, login, atención y fake.
6. Desactiva mantenimiento.
7. Documenta punto de restauración y pérdida de datos.

## Rotar secretos

1. Deshabilita envíos.
2. Rota App Secret/token/verify token en Meta.
3. Actualiza la aplicación.
4. Si cambia App Secret, vuelve a validar webhook.
5. Limpia/configura cache.
6. Prueba y habilita.
7. Revoca secretos antiguos.

Cambiar `APP_KEY` requiere desencriptar o volver a introducir tokens cifrados; no la rotes sin un plan de migración.

## Modo mantenimiento

```bash
php artisan down --secret="TOKEN_ALEATORIO"
php artisan up
```

La URL secreta es temporal y no se comparte públicamente.

## Actualización segura

1. Backup.
2. Fake/envíos deshabilitados si afecta mensajería.
3. `php artisan down`.
4. Código y Composer.
5. Assets compilados.
6. Migraciones `--force`.
7. Caches.
8. Suite smoke.
9. `php artisan up`.
10. Monitoreo de errores, cola y webhook.
