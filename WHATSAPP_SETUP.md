# Configuración oficial de Meta WhatsApp Cloud API

Esta guía usa únicamente recursos oficiales de Meta. La interfaz de Meta puede cambiar; confirma cada paso en la documentación enlazada antes de producción.

Fuentes oficiales:

- [Cloud API: primeros pasos](https://developers.facebook.com/docs/whatsapp/cloud-api/get-started)
- [Configuración de webhooks](https://developers.facebook.com/docs/whatsapp/cloud-api/guides/set-up-webhooks)
- [Enviar mensajes](https://developers.facebook.com/documentation/business-messaging/whatsapp/messages/send-messages)
- [Changelog Graph API v25.0](https://developers.facebook.com/docs/graph-api/changelog/version25.0)
- [Colección oficial de Meta en Postman](https://www.postman.com/meta/whatsapp-business-platform/overview)

## 1. Preparar Meta

1. Crea o selecciona un Meta Business Portfolio.
2. En Meta for Developers crea una app de tipo Business.
3. Añade el producto WhatsApp.
4. En API Setup usa primero el número de prueba de Meta.
5. Identifica:
   - WABA ID;
   - Phone Number ID;
   - número visible;
   - access token;
   - App Secret de la app.
6. Para producción agrega y verifica un número que pueda recibir el código de registro. Cada negocio de la plataforma debe usar su propia cuenta/número.
7. Completa verificación empresarial, permisos y revisión que Meta solicite.

No uses APIs que simulen WhatsApp Web, sesiones QR, Baileys ni WPPConnect.

## 2. Variables

```env
WHATSAPP_PROVIDER=meta
WHATSAPP_SEND_ENABLED=false
META_GRAPH_API_VERSION=v25.0
META_WABA_ID=REEMPLAZAR
META_PHONE_NUMBER_ID=REEMPLAZAR
META_ACCESS_TOKEN=REEMPLAZAR
META_APP_SECRET=REEMPLAZAR
META_WEBHOOK_VERIFY_TOKEN=GENERAR_UN_VALOR_ALEATORIO
```

La aplicación usa v25.0 porque la documentación oficial indica que fue publicada el 18 de febrero de 2026. La versión está centralizada y debe revisarse antes de cada actualización.

En el panel, los secretos quedan cifrados. No los escribas en tickets, capturas, logs o Git.

## 3. Webhook

URL:

```text
https://TU_DOMINIO/api/webhooks/whatsapp
```

En Meta configura:

1. Callback URL HTTPS pública.
2. El mismo verify token configurado en la aplicación.
3. Suscripción al campo `messages` del WABA.
4. Verificación GET correcta: Meta envía mode/token/challenge.
5. Envío POST de prueba.

La aplicación valida `X-Hub-Signature-256` con HMAC-SHA256 y el App Secret. Una firma ausente o inválida responde 403. Mensajes repetidos no se procesan dos veces.

## 4. Plantillas

Desde Configuración:

1. Crea el borrador y muestras para todas las variables.
2. Revisa categoría solicitada, idioma, header, body, footer y botones.
3. En Meta mode, envía a revisión.
4. Sincroniza hasta ver `approved`, `rejected`, `paused` o `disabled`.
5. Ajusta y vuelve a enviar si Meta reclasifica o rechaza.

Solo las plantillas Marketing aprobadas pueden usarse en campañas. No se promete aprobación.

## 5. Prueba controlada

1. Mantén `WHATSAPP_SEND_ENABLED=false`.
2. Verifica el health check autenticado.
3. Prueba webhook y plantillas con el número de prueba de Meta.
4. Usa un destinatario autorizado por Meta.
5. Habilita temporalmente envíos reales.
6. Registra una atención y confirma mensaje, Meta ID y webhook de estado.
7. Prueba `SALDO`, `PREMIOS`, `SALIR` y `AYUDA`.
8. Confirma que `SALIR` crea una revocación histórica.
9. Deshabilita otra vez mientras completas la revisión.

## 6. Paso de fake a Meta

1. Respalda base y `.env`.
2. Configura WABA, Phone Number ID, token, App Secret y verify token.
3. Publica webhook HTTPS.
4. Sincroniza plantillas.
5. Cambia el proveedor del negocio a Meta.
6. Activa `WHATSAPP_SEND_ENABLED=true` solo después de todas las pruebas.
7. Monitorea mensajes fallidos y `failed_jobs`.

Los tokens pueden vencer o revocarse. Usa tokens adecuados para producción, aplica mínimo privilegio y define rotación.
