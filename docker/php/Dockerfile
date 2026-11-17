FROM php:8.3-fpm

ARG UID=1000
ARG GID=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
        libicu-dev libonig-dev default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql gd zip intl bcmath mbstring exif pcntl opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/zz-app.ini

RUN groupmod -o -g ${GID} www-data && usermod -o -u ${UID} -g www-data www-data \
    && mkdir -p /var/www/.config /var/www/.composer && chown -R www-data:www-data /var/www/.config /var/www/.composer

WORKDIR /var/www/html
USER www-data
