#!/bin/sh

set -eu

cd /var/www/html

case "${APP_KEY:-}" in
    base64:*) ;;
    "") echo "APP_KEY no está configurada." >&2; exit 1 ;;
    *) export APP_KEY="base64:${APP_KEY}" ;;
esac

mkdir -p \
    bootstrap/cache \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

chown -R www-data:www-data bootstrap/cache storage

php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan migrate --force --no-interaction
php artisan app:seed-demo --force --no-interaction
php artisan storage:link --no-interaction || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec /usr/bin/supervisord -c /etc/supervisord.conf
