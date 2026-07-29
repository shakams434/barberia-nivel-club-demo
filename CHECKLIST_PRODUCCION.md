# Checklist de producción

## Infraestructura

- [ ] Dominio y Stellar Plus activos.
- [ ] SSL válido y redirección HTTPS.
- [ ] PHP 8.3 o superior con extensiones requeridas.
- [ ] Proyecto fuera de `public_html`; solo `public` expuesto.
- [ ] MySQL/MariaDB y usuario exclusivo creados.
- [ ] Copia de seguridad inicial guardada.

## Aplicación

- [ ] `APP_ENV=production`.
- [ ] `APP_DEBUG=false`.
- [ ] `APP_URL` usa HTTPS.
- [ ] `APP_KEY` única y respaldada.
- [ ] Sesiones cifradas y cookie segura.
- [ ] Cola y sesiones usan base de datos.
- [ ] SMTP probado.
- [ ] `npm run build` completado.
- [ ] `composer install --no-dev --optimize-autoloader` completado.
- [ ] `php artisan migrate --force` completado.
- [ ] Administrador creado con `app:create-admin`.
- [ ] `php artisan app:check-production` sin observaciones.

## Operación

- [ ] Cron cada cinco minutos.
- [ ] Scheduler y worker de corta duración verificados.
- [ ] Servicios, XP, niveles, rangos y recompensas revisados.
- [ ] Horario y tamaño de lotes definidos.
- [ ] Inicio, cierre y recuperación de contraseña probados.
- [ ] Alta, atención, canje y reversión probados.
- [ ] Móvil de 360 y 390 px probado sin desplazamiento horizontal.

## WhatsApp

- [ ] Aplicación y número de Meta configurados.
- [ ] Tokens únicamente en `.env` y campos cifrados.
- [ ] Webhook verificado y firma validada.
- [ ] Plantillas aprobadas.
- [ ] Mensaje al teléfono autorizado entregado.
- [ ] `WHATSAPP_SEND_ENABLED=true` solo al finalizar.
- [ ] Campaña de una persona autorizada verificada.

## Datos personales

- [ ] Aviso de privacidad publicado.
- [ ] Consentimiento operativo validado.
- [ ] Consentimiento de promociones validado por separado.
- [ ] Procedimiento para derechos de titulares definido.
- [ ] Retención, copias y accesos documentados.
- [ ] Registro del banco de datos personales revisado.
- [ ] Revisión legal completada.
