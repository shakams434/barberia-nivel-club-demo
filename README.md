# Nivel Club

Plataforma multiempresa de fidelidad por WhatsApp para barberías y negocios de servicios. El administrador registra clientes y atenciones desde móvil o laptop; el cliente recibe XP, nivel, rango y recompensas por la API oficial de WhatsApp Cloud API.

## Funciones principales

- Panel administrativo en español, responsive y accesible.
- Clientes con búsqueda, filtros, selección controlada, exportación y anonimización.
- Consentimiento operativo y de promociones por separado, con historial y versión.
- Teléfono protegido mediante cifrado, hash de búsqueda y últimos cuatro dígitos.
- Atenciones idempotentes, XP auditable, niveles, rangos y recompensas.
- Canjes y reversiones con motivo, administrador y fecha.
- Campañas con plantilla aprobada, consentimiento, frecuencia, lotes y horarios.
- Webhook firmado, eventos idempotentes, reintentos y estados de entrega.
- Asistente de conexión con validación real de WABA y Phone Number ID, secretos cifrados y activación controlada.
- Bandeja bidireccional responsive con no leídos, búsqueda, historial conjunto y control de la ventana de respuesta de 24 horas.
- Comandos determinísticos: `SALDO`, `NIVEL`, `PREMIOS`, `AYUDA` y `SALIR`.
- Preparación para cPanel, MySQL/MariaDB y Cron cada cinco minutos.

No utiliza inteligencia artificial, Redis, Docker, WhatsApp Web ni integraciones no oficiales.

## Requisitos

- PHP 8.3 o superior con `curl`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql` y `zip`.
- Composer 2.
- Node.js 20 o superior únicamente para compilar assets.
- MySQL/MariaDB en producción. SQLite puede usarse en desarrollo y pruebas.

## Instalación local

```bash
composer install
copy .env.example .env
php artisan key:generate
```

Para usar SQLite localmente:

```env
DB_CONNECTION=sqlite
DB_DATABASE=C:/RUTA/ABSOLUTA/database/database.sqlite
WHATSAPP_PROVIDER=fake
WHATSAPP_SEND_ENABLED=false
```

Después:

```bash
php artisan migrate
php artisan storage:link
php artisan app:create-admin
npm install
npm run build
php artisan serve
```

El comando `app:create-admin` solicita la contraseña de forma oculta y crea la configuración base del negocio. No existen credenciales predeterminadas.

Los datos locales de práctica son opcionales y están bloqueados en producción. Para cargarlos debe habilitarse expresamente `ALLOW_LOCAL_SAMPLE_DATA=true` y definir todas las variables `DEMO_ADMIN_*` con valores propios antes de ejecutar `php artisan db:seed`.

## Verificación

```bash
php artisan test
vendor/bin/pint --test
npm run build
php artisan schedule:list
php artisan app:check-production
```

`app:check-production` fallará de forma intencional mientras existan ajustes incompatibles con producción.

## Namecheap

Genera un paquete reproducible:

```bash
php scripts/build-namecheap-package.php
```

Resultado: `dist/barber-loyalty-namecheap.zip`.

El ZIP no contiene `.env`, contraseñas, tokens, datos locales, logs, cachés, `node_modules`, pruebas ni `vendor`. Ejecuta Composer en el servidor como explica [DEPLOY_NAMECHEAP.md](DEPLOY_NAMECHEAP.md).

## Documentación

- [DEPLOY_NAMECHEAP.md](DEPLOY_NAMECHEAP.md)
- [WHATSAPP_META_SETUP.md](WHATSAPP_META_SETUP.md)
- [CHECKLIST_PRODUCCION.md](CHECKLIST_PRODUCCION.md)
- [CHECKLIST_DATOS_PERSONALES_PERU.md](CHECKLIST_DATOS_PERSONALES_PERU.md)
- [ARCHITECTURE.md](ARCHITECTURE.md)
- [RUNBOOK.md](RUNBOOK.md)

Los modelos legales incluidos requieren revisión profesional antes de utilizar información de clientes reales.
