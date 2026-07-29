# Plantillas de WhatsApp

Meta decide la categoría y aprobación final. Las plantillas incluidas son borradores operativos; pueden requerir adaptación.

## `loyalty_welcome`

- Categoría solicitada: Utility
- Variables: cliente, negocio, nivel inicial.
- Propósito: confirmar inscripción y explicar comandos.

```text
Hola {{1}}. Tu inscripción en {{2}} está confirmada.

Comienzas en Nivel {{3}}. Responde SALDO, NIVEL, PREMIOS o AYUDA.
```

## `loyalty_xp_update`

- Categoría solicitada: Utility
- Variables: cliente, negocio, XP, nivel, rango, progreso.
- No contiene promociones.

```text
Hola {{1}}. Registramos una nueva atención en {{2}}.

Ganaste {{3}} XP.
Tu estado actual es Nivel {{4}} · {{5}}.
Progreso al siguiente nivel: {{6}}%.
```

## `loyalty_level_up`

- Categoría solicitada: Utility
- Header: imagen opcional para tarjeta de nivel.
- Variables: cliente, nivel, rango, negocio, recompensa.

```text
Subiste de nivel, {{1}}.

Ahora eres Nivel {{2}} · {{3}} en {{4}}.
Tu recompensa disponible es: {{5}}.
```

Si GD o el medio falla, se envía texto.

## `campaign_level_discount`

- Categoría: Marketing
- Variables: cliente, nivel, rango, descuento, servicio, fecha.

```text
Hola {{1}}.

Por ser Nivel {{2}} · {{3}}, tienes {{4}}% de descuento en {{5}} hasta el {{6}}.

Reserva tu atención desde el botón.
```

Pie:

```text
Puedes dejar de recibir promociones respondiendo SALIR.
```

## Variables y muestras

- Usa `{{1}}`, `{{2}}`… sin saltos.
- Incluye una muestra realista por variable, sin datos personales reales.
- No pongas variables consecutivas sin contexto.
- No conviertas una plantilla Utility en promoción mediante variables.
- La aplicación valida cantidad y secuencia antes de enviar.

## Estados

- `draft`: solo local.
- `pending`: enviado a revisión.
- `approved`: elegible para el uso aprobado.
- `rejected`: revisar motivo y adaptar.
- `paused` / `disabled`: no enviar.

## Utility, Marketing y ventana de 24 horas

Utility cubre una transacción o servicio solicitado; Marketing promociona una oferta. La clasificación depende de Meta. Fuera de la ventana de servicio iniciada por el cliente se usa una plantilla aprobada. Dentro de la ventana, las respuestas determinísticas pueden enviarse como texto de sesión cuando corresponda.

Consulta las reglas actuales en la [documentación oficial de plantillas de Meta](https://developers.facebook.com/docs/whatsapp/business-management-api/message-templates) y [mensajes de Cloud API](https://developers.facebook.com/documentation/business-messaging/whatsapp/messages/send-messages).

## Consentimiento y opt-out

Una plantilla aprobada no reemplaza el consentimiento:

- Loyalty y Marketing son independientes.
- Las campañas revalidan Marketing justo antes de enviar.
- `SALIR` revoca Marketing inmediatamente.
- El límite predeterminado es 2 promociones cada 30 días.
- Nunca intentes evadir filtros de spam o calidad de Meta.
