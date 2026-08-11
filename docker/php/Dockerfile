ARG PHP_VERSION=8.4
ARG PHP_IMAGE_DIGEST=sha256:9467f10bf42897dec0abb73ee20c747ebd45463ec9d6fcc4044cb83eba6dade7

FROM php:${PHP_VERSION}-fpm@${PHP_IMAGE_DIGEST}

ARG USER_ID=1000
ARG GROUP_ID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
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

COPY --from=composer:2@sha256:4d71c3c2109c61d5415544264b59ad4087e4c5b7244481723664138fd36d5040 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-campement.ini
COPY docker/php/entrypoint.sh /usr/local/bin/campement-entrypoint
COPY docker/php/maintenance.sh /usr/local/bin/campement-maintenance

RUN chmod +x /usr/local/bin/campement-entrypoint /usr/local/bin/campement-maintenance

WORKDIR /var/www/app

ENTRYPOINT ["campement-entrypoint"]
CMD ["php-fpm"]
