# Configuración de WhatsApp Cloud API

La aplicación usa exclusivamente la API oficial de Meta. No necesita WhatsApp Web, QR de sesión ni librerías no oficiales.

## Requisitos externos

1. Cuenta de Meta Business.
2. Portafolio comercial y aplicación en Meta for Developers.
3. Producto WhatsApp agregado a la aplicación.
4. Número autorizado para WhatsApp Cloud API.
5. WABA ID, Phone Number ID, Access Token, App Secret y un Verify Token propio.

No pegues estos valores en tickets, logs, capturas o repositorios.

## Configuración inicial

Mantén los envíos deshabilitados:

```env
WHATSAPP_PROVIDER=meta
WHATSAPP_SEND_ENABLED=false
META_GRAPH_API_VERSION=v25.0
META_WABA_ID=<WABA_ID>
META_PHONE_NUMBER_ID=<PHONE_NUMBER_ID>
META_ACCESS_TOKEN=<TOKEN>
META_APP_SECRET=<APP_SECRET>
META_WEBHOOK_VERIFY_TOKEN=<TOKEN_ALEATORIO>
```

En la aplicación abre **Configuración → WhatsApp**, guarda los mismos datos y deja desmarcada la activación de envíos.

## Webhook

En Meta configura:

```text
Callback URL: https://<TU_DOMINIO>/api/webhooks/whatsapp
Verify token: el valor de META_WEBHOOK_VERIFY_TOKEN
```

Suscribe al menos `messages`. La aplicación:

- valida `X-Hub-Signature-256`;
- vincula el Phone Number ID con el negocio;
- deduplica mensajes y estados;
- guarda solo una huella del evento;
- procesa los comandos en cola;
- responde rápidamente a Meta.

## Plantillas

Crea o revisa plantillas para:

- bienvenida;
- actualización de XP;
- subida de nivel;
- recompensa desbloqueada;
- confirmación de canje;
- baja de promociones;
- campañas.

Las campañas deben utilizar una plantilla de marketing aprobada. Meta puede reclasificar o rechazar una plantilla; la aplicación no asume una categoría final.

En **Configuración → Plantillas**:

1. crea el borrador;
2. completa ejemplos para cada `{{n}}`;
3. envíalo a Meta;
4. espera la revisión;
5. actualiza el estado;
6. usa únicamente plantillas aprobadas.

## Activación controlada

1. Configura el teléfono autorizado del administrador en **Negocio**.
2. Usa **Enviar mensaje de prueba**.
3. Revisa la bandeja y Meta Business Manager.
4. Confirma que el webhook recibe `sent`, `delivered` y `read`.
5. Cambia `WHATSAPP_SEND_ENABLED=true`.
6. Limpia caché: `php artisan optimize:clear && php artisan config:cache`.
7. Activa los envíos reales en la pantalla de WhatsApp.

Los dos interruptores deben estar activos. Esto evita habilitaciones accidentales.

## Campañas

- Solo clientes activos con consentimiento vigente.
- Envío gradual por lotes y Cron cada cinco minutos.
- Frecuencia y horario configurables.
- Revalidación del consentimiento justo antes del envío.
- `SALIR` excluye de inmediato futuras promociones.
- Pausar o cancelar evita preparar nuevos destinatarios.

## Diagnóstico

```bash
php artisan queue:work database --stop-when-empty --tries=3
php artisan schedule:run
php artisan app:check-production
```

Revisa **Mensajes** sin copiar tokens ni números completos. Si Meta falla, la atención, el XP y el canje permanecen registrados; el mensaje queda en cola o con error sanitizado.
