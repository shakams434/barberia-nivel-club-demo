# Arquitectura

## Forma del sistema

Monolito Laravel 13 con Blade/Livewire, MySQL/MariaDB y Vite. Está diseñado para hosting compartido: las solicitudes web son cortas, la cola usa base de datos y todo proceso diferido termina por sí solo.

Capas principales:

- HTTP: controladores pequeños, validación, autenticación y middleware de tenant.
- UI: Blade + Livewire mobile-first.
- Dominio: `LoyaltyEngine`, `ConsentService`, `CampaignService`, `InboundMessageProcessor`.
- Integraciones: contrato `WhatsAppProviderInterface`, proveedor fake y proveedor Meta.
- Asíncrono: jobs idempotentes, `jobs`, `failed_jobs` y Scheduler.
- Persistencia: Eloquent, transacciones, ledger de XP y auditoría append-only.

## Multi-tenant

Cada entidad empresarial contiene `business_id`. `SetTenantContext` toma el negocio del usuario autenticado y `BelongsToBusiness` agrega un global scope a todas las consultas de dominio. El scope también completa `business_id` al crear.

Los usuarios no usan el scope durante el login, pero cada usuario pertenece obligatoriamente a un negocio. Controladores y servicios verifican pertenencia en acciones críticas. Jobs guardan el ID del recurso, restauran el tenant antes de consultar y lo limpian al terminar.

No se usan subdominios ni bases separadas; la frontera es por fila. Una evolución futura puede mover negocios grandes a bases separadas manteniendo los servicios de dominio.

## Modelo de datos

- `businesses`, `users`: identidad y tenant.
- `customers`, `consents`: perfil, estado y autorización histórica.
- `services`, `loyalty_programs`, `tiers`, `rewards`: reglas configurables.
- `visits`, `loyalty_transactions`: atención e historial contable de XP.
- `customer_rewards`, `reward_redemptions`: desbloqueo y canje.
- `whatsapp_accounts`, `whatsapp_templates`, `whatsapp_messages`, `inbound_messages`: integración oficial y trazabilidad.
- `campaigns`, `campaign_recipients`: segmentación y resultados.
- `audit_logs`, `jobs`, `failed_jobs`: control operativo.

`loyalty_transactions` es la fuente de verdad. `customers.xp_total` y `level` son proyecciones transaccionales para lectura rápida. Las restricciones únicas cubren teléfono por negocio, claves de idempotencia, mensaje Meta entrante y destinatario por campaña.

## Flujo de atención

1. Livewire bloquea el botón y envía una idempotency key.
2. `LoyaltyEngine` verifica tenant y atención reciente.
3. Dentro de una transacción bloquea al cliente, crea visita y movimiento, recalcula nivel/rango y desbloquea recompensas.
4. Se registra auditoría y se emite `VisitRegistered` después del commit.
5. El listener genera tarjeta si corresponde, crea el mensaje y hace un intento corto.
6. Si falla, `SendWhatsAppMessage` queda en la cola con backoff.
7. Una corrección crea un movimiento inverso; nunca borra la visita.

## Flujo de campañas

1. El borrador exige plantilla Marketing aprobada.
2. La audiencia filtra nivel, rango, inactividad y recompensa.
3. `CampaignService` incluye solo consentimientos vigentes y deduplica.
4. Al confirmar se crean destinatarios únicos.
5. Cron ejecuta `campaigns:dispatch`; `ProcessCampaignBatch` procesa el tamaño configurado.
6. Antes de cada envío se revalidan estado, consentimiento y límite de frecuencia.
7. Cada mensaje va a la cola. Los webhooks actualizan sent/delivered/read/failed.

## Flujo de webhooks

1. GET compara verify token y devuelve challenge.
2. POST localiza la cuenta por Phone Number ID y valida `X-Hub-Signature-256` sobre el cuerpo sin modificar.
3. Mensajes entrantes se guardan por Meta message ID único y encolan `ProcessInboundWhatsAppMessage`.
4. El procesador normaliza el comando y responde sin IA.
5. Los eventos de estado actualizan mensaje y destinatario.

## Seguridad

- Contraseñas hasheadas por Laravel, sesiones regeneradas, CSRF y rate limit.
- Tokens Meta cifrados mediante el cifrador de Laravel.
- CSP, HSTS en producción, `nosniff`, frame policy y referrer policy.
- Salida Blade escapada, validación estricta y mass-assignment explícito.
- Logs sin secretos; la UI enmascara teléfonos.
- UUID públicos para rutas de clientes, campañas y mensajes.
- Confirmación y auditoría para canjes, reversión y anonimización.

## Hosting compartido

No hay Redis, Horizon, Supervisor ni daemon. El Cron de cinco minutos llama `schedule:run`; el Scheduler encola campañas y ejecuta `queue:work --stop-when-empty --max-time=240`. Las notificaciones individuales intentan enviarse durante la solicitud con timeout de cinco segundos y caen a cola.

## Escalamiento futuro

Cuando el volumen lo requiera:

- mover cola y cache a Redis sin cambiar contratos;
- ejecutar workers administrados;
- particionar mensajes/auditoría;
- separar almacenamiento de tarjetas;
- añadir rate limiting por WABA y métricas de observabilidad;
- extraer mensajería como servicio manteniendo idempotency keys.

Estas mejoras no son requisito de Namecheap Stellar.
