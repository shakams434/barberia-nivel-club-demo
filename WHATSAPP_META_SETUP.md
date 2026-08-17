# Configuración de WhatsApp Cloud API

La aplicación usa exclusivamente la API oficial de Meta. No necesita WhatsApp Web, QR de sesión ni librerías no oficiales.

## Requisitos externos

1. Cuenta de Meta Business.
2. Portafolio comercial y aplicación en Meta for Developers.
3. Producto WhatsApp agregado a la aplicación.
4. Número autorizado para WhatsApp Cloud API.
5. WABA ID, Phone Number ID, token permanente de un usuario del sistema y App Secret.

El usuario del sistema debe tener los permisos `whatsapp_business_management` y `whatsapp_business_messaging`. La plataforma genera por sí sola el token de verificación del webhook; el administrador no tiene que inventarlo.

No pegues estos valores en tickets, logs, capturas o repositorios.

## Configuración inicial

En la aplicación abre **WhatsApp → Conexión**. El asistente solicita únicamente los cuatro datos anteriores, consulta la API oficial de Meta y comprueba que el número realmente pertenezca al WABA antes de guardar nada.

El número visible, el nombre verificado y la calidad se leen desde Meta. El token y el App Secret se cifran en el servidor y nunca vuelven a mostrarse completos. No deben duplicarse en el archivo `.env`.

## Webhook

En Meta configura:

```text
Callback URL: https://<TU_DOMINIO>/api/webhooks/whatsapp
Verify token: el valor generado y mostrado por la plataforma
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

La creación y aprobación se realiza en **WhatsApp Manager**. Después, en **Configuración → Plantillas**:

1. registra el nombre técnico e idioma exactamente como aparecen en Meta;
2. copia el contenido aprobado;
3. completa ejemplos solo si la plantilla usa variables `{{n}}`;
4. confirma que ya está activa en WhatsApp Manager;
5. asígnala a una campaña o automatización.

## Activación controlada

1. Guarda y comprueba los cuatro datos con Meta.
2. Copia la URL y el token de verificación en la configuración del webhook de Meta.
3. Pulsa **Suscribir aplicación al WABA**.
4. Envía un WhatsApp desde un teléfono al número conectado.
5. Confirma que aparece en **WhatsApp → Conversaciones**.
6. Pulsa **Activar WhatsApp**.

La activación permanece bloqueada hasta que la cuenta esté validada, el webhook esté suscrito y la plataforma haya recibido al menos un evento real.

## Conversaciones

- Los mensajes entrantes y salientes aparecen en una sola línea de tiempo.
- Un agente puede responder texto libre durante las 24 horas posteriores al último mensaje del cliente.
- Al vencer esa ventana, la pantalla bloquea el texto libre y pide usar una plantilla aprobada.
- Los comandos conocidos (`SALDO`, `NIVEL`, `PREMIOS`, `AYUDA`, `SALIR`) pueden responderse automáticamente. Los demás mensajes esperan a una persona para evitar respuestas dobles.
- Los teléfonos se muestran enmascarados y los usuarios con rol agente no pueden cambiar credenciales.

## Campañas

- Solo clientes activos con consentimiento vigente.
- Envío gradual por lotes y Cron cada cinco minutos.
- Frecuencia y horario configurables.
- Revalidación del consentimiento justo antes del envío.
- `SALIR` excluye de inmediato futuras promociones.
- Pausar o cancelar evita preparar nuevos destinatarios.

## Diagnóstico

```bash
php artisan queue:work database --queue=webhooks,campaigns,messages,default --stop-when-empty --tries=3
php artisan schedule:run
php artisan app:check-production
```

Revisa **WhatsApp → Historial de envíos** sin copiar tokens ni números completos. Si Meta falla, la atención, el XP y el canje permanecen registrados; el mensaje queda en cola o con error sanitizado.
