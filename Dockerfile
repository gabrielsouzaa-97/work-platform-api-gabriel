# syntax=docker/dockerfile:1.7
# mework360-deployer — Multi-stage Dockerfile (Laravel 12 + PHP 8.3 + PostgreSQL + Redis)
# Stages: base → development → build → production

# ============================================================================
# BASE — runtime mínimo PHP-FPM com extensões necessárias
# ============================================================================
FROM php:8.3-fpm-alpine AS base

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0

RUN apk add --no-cache \
        bash \
        curl \
        git \
        icu-libs \
        libpng \
        libjpeg-turbo \
        libwebp \
        libzip \
        oniguruma \
        postgresql-libs \
        tini \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        autoconf \
        icu-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        libwebp-dev \
        libzip-dev \
        linux-headers \
        oniguruma-dev \
        postgresql-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_pgsql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/* /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ============================================================================
# DEVELOPMENT — inclui Xdebug, código montado via volume, php artisan serve
# ============================================================================
FROM base AS development

ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS=1 \
    XDEBUG_MODE=off \
    XDEBUG_CLIENT_HOST=host.docker.internal

RUN apk add --no-cache --virtual .xdebug-deps $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del .xdebug-deps \
    && rm -rf /tmp/* /var/cache/apk/*

EXPOSE 9000

ENTRYPOINT ["/sbin/tini", "--"]
CMD ["php-fpm"]

# ============================================================================
# BUILD — instala deps de produção, otimiza autoload (sem rodar artisan cache,
# pois o config:cache só funciona após APP_KEY presente em runtime)
# ============================================================================
FROM base AS build

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative \
    && rm -rf tests docs layout .cursor .github

# ============================================================================
# PRODUCTION — usuário não-root, sem dev deps, healthcheck
# ============================================================================
FROM base AS production

ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS=0 \
    APP_ENV=production \
    APP_DEBUG=false

RUN addgroup -g 1000 appuser \
    && adduser -u 1000 -G appuser -s /bin/sh -D appuser

COPY --from=build --chown=appuser:appuser /var/www/html /var/www/html

RUN mkdir -p /var/www/html/storage/framework/{cache,sessions,views} \
             /var/www/html/storage/logs \
             /var/www/html/bootstrap/cache \
    && chown -R appuser:appuser /var/www/html/storage /var/www/html/bootstrap/cache

USER appuser

EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD php artisan about --json > /dev/null 2>&1 || exit 1

ENTRYPOINT ["/sbin/tini", "--"]
CMD ["php-fpm"]
