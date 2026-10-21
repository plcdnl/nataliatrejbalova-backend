# syntax=docker/dockerfile:1
# Craft project Dockerfile: the whole runtime setup (extensions, nginx,
# supervisor queue, cron, php.ini) lives in the base image. Only
# project-specific bits go here.
ARG php_version=8.4
# Base build to use: "latest" or a date (e.g. 20261021) to pin a project to a
# specific build (APP_BASE_DATE env on Coolify).
ARG base_date=latest

# -----------------------------------------------------------------------------
# vendor: composer install + code. The composer layer stays cached until
# composer.json/composer.lock change.
# -----------------------------------------------------------------------------
FROM ghcr.io/rondodevs/craft-php:${php_version}-${base_date}-worker AS vendor

COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/composer/cache \
    COMPOSER_CACHE_DIR=/composer/cache composer install \
    --no-interaction \
    --prefer-dist \
    --no-dev \
    --no-scripts \
    --no-ansi \
    --no-progress \
    --optimize-autoloader

# vendor/ must be excluded in .dockerignore (already installed above).
COPY --chown=www-data:www-data . .

# Writable directories mounted as volumes: Docker seeds an empty named volume
# with the ownership/permissions already present in the image on first mount.
RUN mkdir -p storage web/uploads \
    && chown -R www-data:www-data storage web/uploads \
    && chmod -R 775 storage web/uploads

# -----------------------------------------------------------------------------
# worker: Craft queue (supervisor)
# -----------------------------------------------------------------------------
FROM ghcr.io/rondodevs/craft-php:${php_version}-${base_date}-worker AS worker
COPY --from=vendor /app /app
USER www-data

# -----------------------------------------------------------------------------
# web: nginx + php-fpm. Default target.
# -----------------------------------------------------------------------------
FROM ghcr.io/rondodevs/craft-php:${php_version}-${base_date}-web AS web
COPY --from=vendor /app /app
# s6-overlay in the base image replaces USER with its own init (root), but
# the Coolify/docker exec interactive terminal uses the Dockerfile USER.
USER www-data
