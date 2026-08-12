ARG PHP_VERSION=8.4
ARG PHP_IMAGE_DIGEST=sha256:9467f10bf42897dec0abb73ee20c747ebd45463ec9d6fcc4044cb83eba6dade7
ARG NGINX_VERSION=1.29-alpine
ARG NGINX_IMAGE_DIGEST=sha256:5616878291a2eed594aee8db4dade5878cf7edcb475e59193904b198d9b830de

FROM php:${PHP_VERSION}-fpm@${PHP_IMAGE_DIGEST} AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        linux-libc-dev \
        libpng-dev \
        libpq-dev \
        libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        gd \
        intl \
        opcache \
        pdo_pgsql \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-campement.ini

WORKDIR /var/www/app

FROM php-base AS php-development

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2@sha256:4d71c3c2109c61d5415544264b59ad4087e4c5b7244481723664138fd36d5040 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/entrypoint.sh /usr/local/bin/campement-entrypoint

RUN chmod +x /usr/local/bin/campement-entrypoint

ENTRYPOINT ["campement-entrypoint"]
CMD ["php-fpm"]

FROM php-base AS app-builder

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2@sha256:4d71c3c2109c61d5415544264b59ad4087e4c5b7244481723664138fd36d5040 /usr/bin/composer /usr/local/bin/composer
COPY app/composer.json app/composer.lock app/symfony.lock ./

RUN composer install \
        --no-dev \
        --no-autoloader \
        --no-scripts \
        --no-interaction \
        --prefer-dist \
        --no-progress

COPY app/ ./

RUN : > .env \
    && composer dump-autoload \
        --no-dev \
        --classmap-authoritative \
        --no-scripts \
        --no-interaction \
    && APP_ENV=prod \
       APP_SECRET=build-only-not-used-at-runtime \
       DATABASE_URL=postgresql://build:build@database:5432/build?serverVersion=18\&charset=utf8 \
       DEFAULT_URI=http://localhost:8080 \
       MAILER_DSN=null://null \
       MESSENGER_TRANSPORT_DSN=sync:// \
       TRUSTED_HOST_PATTERN='^(localhost|127\\.0\\.0\\.1)$' \
       TRUSTED_PROXIES=127.0.0.1 \
       php bin/console importmap:install --no-interaction \
    && APP_ENV=prod \
       APP_SECRET=build-only-not-used-at-runtime \
       DATABASE_URL=postgresql://build:build@database:5432/build?serverVersion=18\&charset=utf8 \
       DEFAULT_URI=http://localhost:8080 \
       MAILER_DSN=null://null \
       MESSENGER_TRANSPORT_DSN=sync:// \
       TRUSTED_HOST_PATTERN='^(localhost|127\\.0\\.0\\.1)$' \
       TRUSTED_PROXIES=127.0.0.1 \
       php bin/console asset-map:compile --no-debug --no-interaction \
    && rm -rf var/cache/* var/log/* \
    && install -d -o www-data -g www-data -m 0770 \
        var/cache var/log var/documents_participants

FROM php-base AS php-production

COPY docker/php/maintenance.sh /usr/local/bin/campement-maintenance
COPY --from=app-builder --chown=www-data:www-data /var/www/app /var/www/app

RUN chmod 0755 /usr/local/bin/campement-maintenance

USER www-data

ENTRYPOINT ["docker-php-entrypoint"]
CMD ["php-fpm"]

FROM nginx:${NGINX_VERSION}@${NGINX_IMAGE_DIGEST} AS nginx-production

RUN apk upgrade --no-cache

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app-builder /var/www/app/public /var/www/app/public
