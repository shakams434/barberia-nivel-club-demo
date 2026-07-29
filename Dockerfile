# syntax=docker/dockerfile:1

FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY public ./public
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM php:8.3-cli-alpine AS vendor

WORKDIR /app

RUN apk add --no-cache curl git unzip \
    && curl -fsSL https://getcomposer.org/installer -o composer-setup.php \
    && EXPECTED_CHECKSUM="$(curl -fsSL https://composer.github.io/installer.sig)" \
    && ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")" \
    && [ "$EXPECTED_CHECKSUM" = "$ACTUAL_CHECKSUM" ] \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm composer-setup.php

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader \
    --prefer-dist

COPY . .
RUN composer dump-autoload \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --classmap-authoritative

FROM php:8.3-fpm-alpine AS runtime

RUN apk add --no-cache \
        icu-libs \
        libpq \
        nginx \
        oniguruma \
        supervisor \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        oniguruma-dev \
        postgresql-dev \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_pgsql \
    && apk del .build-deps

WORKDIR /var/www/html

COPY --from=vendor --chown=www-data:www-data /app /var/www/html
COPY --from=frontend --chown=www-data:www-data /app/public/build /var/www/html/public/build
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/render-start.sh /usr/local/bin/render-start

RUN chmod +x /usr/local/bin/render-start \
    && mkdir -p \
        /run/nginx \
        /var/www/html/bootstrap/cache \
        /var/www/html/storage/app/public \
        /var/www/html/storage/framework/cache/data \
        /var/www/html/storage/framework/sessions \
        /var/www/html/storage/framework/views \
        /var/www/html/storage/logs \
    && chown -R www-data:www-data \
        /var/www/html/bootstrap/cache \
        /var/www/html/storage

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    PHP_OPCACHE_ENABLE=1

EXPOSE 10000

CMD ["render-start"]
