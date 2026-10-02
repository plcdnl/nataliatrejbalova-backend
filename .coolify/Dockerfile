ARG php_version=8.4

# -----------------------------------------------------------------------------
# base: estensioni PHP e composer install. Nessun layer qui dipende dal codice
# applicativo, quindi resta cached tra un deploy e l'altro anche quando cambia
# solo il codice (a meno di modifiche a composer.json/composer.lock).
# -----------------------------------------------------------------------------
FROM serversideup/php:${php_version}-cli-alpine AS base

ENV PHP_OPCACHE_ENABLE=1
ENV PHP_MAX_INPUT_VARS=5000

USER root

RUN apk add --no-cache ca-certificates \
    && update-ca-certificates \
    && ln -sf /etc/ssl/certs/ca-certificates.crt /etc/ssl/cert.pem \
    && ln -sf /etc/ssl/certs/ca-certificates.crt /etc/ssl/cert.bundle

# Estensioni PHP richieste da Craft
RUN install-php-extensions bcmath gd imagick intl soap

COPY ./.coolify/php/craft.ini /usr/local/etc/php/conf.d/zzz-craft.ini
COPY ./.coolify/php/www-pool.conf /usr/local/etc/php-fpm.d/zzz-pool-user.conf

WORKDIR /app

# Layer separato per sfruttare la cache Docker su composer install
COPY composer.json composer.lock ./
RUN composer install \
  --no-interaction \
  --prefer-dist \
  --no-dev \
  --no-scripts \
  --no-ansi \
  --no-progress \
  --optimize-autoloader

# -----------------------------------------------------------------------------
# vendor: copia il progetto sopra a "base". Unico stage che invalida la cache
# ad ogni deploy (dipende dal codice), quindi tenuto il più leggero possibile.
# -----------------------------------------------------------------------------
FROM base AS vendor

# Copia tutto il progetto preservando la struttura delle directory, impostando
# l'ownership già in fase di COPY per evitare una seconda scansione completa
# dell'albero (chown -R su /app) ad ogni deploy.
# vendor/ è escluso da .dockerignore (già installato dal layer precedente).
COPY --chown=www-data:www-data . .

# Ricrea i mount-point esclusi dal .dockerignore: Docker semina un volume named
# vuoto con proprietario/permessi già presenti nell'immagine al primo mount.
RUN mkdir -p storage web/uploads \
    && chown -R www-data:www-data storage web/uploads \
    && chmod -R 775 storage web/uploads

# -----------------------------------------------------------------------------
# worker: coda Craft, supervisionata da supervisor con restart automatico.
# Parte da "base" (non da "vendor") così apk/su-exec restano cached anche
# quando cambia solo il codice; il progetto viene innestato con COPY --from.
# -----------------------------------------------------------------------------
FROM base AS worker

RUN apk add --no-cache supervisor
RUN docker-php-serversideup-dep-install-alpine "su-exec"

RUN mkdir -p /etc/supervisor/conf.d
COPY ./.coolify/worker/supervisord.conf /etc/supervisor/supervisord.conf
COPY ./.coolify/worker/craft-queue.conf /etc/supervisor/conf.d/craft-queue.conf
RUN ln -sf /etc/supervisor/supervisord.conf /etc/supervisord.conf

COPY --from=vendor /app /app

USER www-data
CMD ["supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]

# -----------------------------------------------------------------------------
# web: nginx + php-fpm. Target di default.
# -----------------------------------------------------------------------------
FROM serversideup/php:${php_version}-fpm-nginx-alpine AS web

ENV PHP_OPCACHE_ENABLE=1
ENV PHP_MAX_INPUT_VARS=5000
# Unico override env necessario in questo stage: php-fpm ascolta su socket
# unix (vedi www-pool.conf) e il readiness probe s6 di cui dipende l'avvio di
# nginx deve puntare allo stesso indirizzo, altrimenti prova "localhost:9000"
# in eterno e nginx non parte mai.
ENV FCGI_CONNECT=/run/php-fpm.sock
# La coda la consuma solo il container "worker": senza questo anche il CP la
# lancerebbe via HTTP, con job concorrenti sulle stesse righe (es. searchindex).
ENV CRAFT_RUN_QUEUE_AUTOMATICALLY=false

USER root

RUN apk add --no-cache ca-certificates \
    && update-ca-certificates \
    && ln -sf /etc/ssl/certs/ca-certificates.crt /etc/ssl/cert.pem \
    && ln -sf /etc/ssl/certs/ca-certificates.crt /etc/ssl/cert.bundle

# mysql-client per backup/restore dal CP; bash per shell interattiva
RUN docker-php-serversideup-dep-install-alpine "mysql-client bash"

SHELL ["/bin/bash", "-c"]

# Estensioni PHP richieste da Craft
RUN install-php-extensions bcmath gd imagick intl soap

# ImageMagick: abilita PDF (transform asset)
RUN find /etc/ImageMagick* -name policy.xml | xargs sed -i 's#rights="none" pattern="PDF"#rights="read|write" pattern="PDF"#g'

COPY ./.coolify/php/craft.ini /usr/local/etc/php/conf.d/zzz-craft.ini
COPY ./.coolify/php/www-pool.conf /usr/local/etc/php-fpm.d/zzz-pool-user.conf

# Nginx: monta la nostra config statica in /etc/nginx/conf.d/default.conf,
# lo stesso path che l'entrypoint della base image controlla prima di generare
# il proprio default.conf — trovandolo già presente, lo lascia stare.
COPY ./.coolify/nginx/nginx.conf /etc/nginx/nginx.conf
COPY ./.coolify/nginx/craftcms /etc/nginx/craftcms
COPY ./.coolify/nginx/sites-available/default.conf /etc/nginx/conf.d/default.conf

# Cleanup giornaliero dei log > 30 giorni via crond (avviato da entrypoint.d)
COPY ./.coolify/php/log-cleanup.sh /usr/local/bin/log-cleanup.sh
RUN chmod +x /usr/local/bin/log-cleanup.sh
COPY ./.coolify/web/crontab /etc/crontabs/root
COPY ./.coolify/web/99-start-crond.sh /etc/entrypoint.d/99-start-crond.sh
RUN chmod +x /etc/entrypoint.d/99-start-crond.sh

WORKDIR /app
COPY --from=vendor /app /app

EXPOSE 8080
# s6-overlay nell'immagine base sovrascrive l'USER con il proprio init (root),
# ma il terminale interattivo di Coolify/docker exec usa l'USER del Dockerfile.
USER www-data
