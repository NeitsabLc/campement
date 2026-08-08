ARG PHP_VERSION=8.4

FROM php:${PHP_VERSION}-fpm

ARG USER_ID=1000
ARG GROUP_ID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
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

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-campement.ini
COPY docker/php/entrypoint.sh /usr/local/bin/campement-entrypoint

RUN chmod +x /usr/local/bin/campement-entrypoint

WORKDIR /var/www/app

ENTRYPOINT ["campement-entrypoint"]
CMD ["php-fpm"]
