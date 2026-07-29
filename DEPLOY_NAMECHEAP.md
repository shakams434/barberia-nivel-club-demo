# Despliegue en Namecheap Shared Hosting Stellar Plus

Esta guía usa cPanel, Apache o LiteSpeed, PHP, MySQL/MariaDB y Cron. Sustituye todos los valores `<ENTRE_ANGULOS>`.

## 1. Preparar la cuenta

1. Compra Stellar Plus y conecta `<TU_DOMINIO>`.
2. Activa AutoSSL desde cPanel y confirma que `https://<TU_DOMINIO>` funciona.
3. Habilita SSH Access o Terminal en cPanel. Si no aparece, solicítalo a soporte.
4. En **Select PHP Version**, elige PHP 8.3 o superior y activa `curl`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql` y `zip`.
5. Desde Terminal ejecuta `which php` y `php -v`. Conserva la ruta mostrada como `<RUTA_PHP>`.

No uses una ruta PHP copiada de otro hosting.

## 2. Crear MySQL

En **MySQL Databases**:

1. Crea `<BASE_DATOS>`.
2. Crea `<USUARIO_BASE>` con contraseña única.
3. Asigna el usuario a la base con **ALL PRIVILEGES**.
4. Anota el nombre completo que agrega cPanel, normalmente `<USUARIO_CPANEL>_<NOMBRE>`.
5. Usa phpMyAdmin solo para inspección o copias, no para importar seeders locales.

## 3. Generar y subir el paquete

En el equipo local:

```bash
npm ci
npm run build
php artisan test
php scripts/build-namecheap-package.php
```

Sube `dist/barber-loyalty-namecheap.zip` a:

```text
/home/<USUARIO_CPANEL>/nivel-club
```

Descomprímelo. Mantén el proyecto fuera de `public_html` siempre que cPanel permita cambiar el document root.

## 4. Instalar dependencias PHP

Desde Terminal:

```bash
cd /home/<USUARIO_CPANEL>/nivel-club
composer install --no-dev --optimize-autoloader --no-interaction
```

Si `composer` no está en PATH, busca la ruta disponible con `which composer` o instala dependencias localmente con la misma versión de PHP y sube `vendor`.

## 5. Exponer solo `public`

Opción recomendada: en cPanel configura el document root de `<TU_DOMINIO>` como:

```text
/home/<USUARIO_CPANEL>/nivel-club/public
```

Así `public/index.php` y `public/.htaccess` funcionan sin cambios.

Si el dominio principal obliga a usar `public_html`, copia únicamente el contenido de `public` a `public_html` y ajusta estas rutas de `public_html/index.php`:

```php
require __DIR__.'/../nivel-club/vendor/autoload.php';
$app = require_once __DIR__.'/../nivel-club/bootstrap/app.php';
```

No copies `.env`, `app`, `config`, `database`, `storage` ni `vendor` dentro de la zona pública.

## 6. Crear `.env`

```bash
cp .env.namecheap.example .env
nano .env
```

Completa como mínimo:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<TU_DOMINIO>
APP_TIMEZONE=America/Lima

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=<BASE_DATOS_COMPLETA>
DB_USERNAME=<USUARIO_BASE_COMPLETO>
DB_PASSWORD=<CONTRASENA_BASE>

QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true

MAIL_MAILER=smtp
MAIL_HOST=<SERVIDOR_SMTP>
MAIL_PORT=465
MAIL_USERNAME=<CORREO>
MAIL_PASSWORD=<CONTRASENA_CORREO>
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=<CORREO>

WHATSAPP_PROVIDER=meta
WHATSAPP_SEND_ENABLED=false
```

Genera la clave:

```bash
php artisan key:generate
```

No reutilices una `APP_KEY` si vas a migrar datos cifrados desde otra instalación. En ese caso conserva la clave original.

## 7. Migrar y crear administrador

```bash
php artisan migrate --force
php artisan app:create-admin
```

No ejecutes `db:seed` en producción.

## 8. Permisos y almacenamiento

```bash
chmod -R 775 storage bootstrap/cache
php artisan storage:link
```

Si el hosting no permite enlaces simbólicos, configura una ruta pública controlada para logos o solicita soporte. No expongas `storage/app/private`.

## 9. Cachés

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Después de cambiar `.env`, repite `php artisan optimize:clear` y reconstruye las cachés.

## 10. Cron cada cinco minutos

En **Cron Jobs** crea una sola entrada:

```cron
*/5 * * * * <RUTA_PHP> /home/<USUARIO_CPANEL>/nivel-club/artisan schedule:run >/dev/null 2>&1
```

El scheduler ejecuta:

- preparación de campañas vencidas;
- un worker de base de datos que termina al vaciar la cola o llegar a 240 segundos.

No requiere Supervisor ni un proceso permanente.

Comprueba:

```bash
php artisan schedule:list
php artisan schedule:run
php artisan queue:work database --stop-when-empty --tries=3 --max-time=240
```

## 11. Configurar Meta

Sigue [WHATSAPP_META_SETUP.md](WHATSAPP_META_SETUP.md). El webhook es:

```text
https://<TU_DOMINIO>/api/webhooks/whatsapp
```

Primero guarda las credenciales, valida el webhook, aprueba las plantillas y envía una prueba al número autorizado. Solo después cambia:

```env
WHATSAPP_SEND_ENABLED=true
```

y activa **Habilitar envíos reales con Meta** en Configuración.

## 12. Comprobación final

```bash
php artisan optimize:clear
php artisan app:check-production
```

Verifica además:

- `https://<TU_DOMINIO>/health` responde `200` sin información sensible.
- inicio y cierre de sesión;
- recuperación de contraseña por SMTP;
- alta de un cliente con consentimiento;
- una atención y su movimiento de XP;
- mensaje de WhatsApp en la bandeja;
- campaña de una sola persona autorizada;
- webhook verificado y firmado;
- ausencia de errores en `storage/logs`.

## 13. Copia de seguridad inicial

Desde cPanel crea una copia de:

- base MySQL;
- `.env` en almacenamiento seguro;
- `storage/app/public`;
- código desplegado.

Programa copias periódicas y prueba una restauración antes de operar con datos reales.

## Actualizaciones futuras

1. Realiza copia de seguridad.
2. Activa mantenimiento: `php artisan down`.
3. Sube el nuevo código sin reemplazar `.env` ni `storage`.
4. Ejecuta `composer install --no-dev --optimize-autoloader`.
5. Ejecuta `php artisan migrate --force`.
6. Reconstruye cachés.
7. Ejecuta `php artisan app:check-production`.
8. Reactiva: `php artisan up`.

Las migraciones agregan o transforman datos de forma controlada; no reemplaces la base con una base local.
