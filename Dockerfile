# Stage 1: Build frontend assets (Vite + Tailwind CSS v4)
FROM node:22-alpine AS frontend
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
RUN npm run build

# Stage 2: PHP 8.4 FPM application image
FROM php:8.4-fpm-alpine AS app
WORKDIR /var/www/html

RUN apk add --no-cache \
        bash \
        curl \
        git \
        icu-dev \
        libpq-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-client \
        unzip \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        pgsql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

COPY . .
COPY --from=frontend /app/public/build ./public/build
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh \
    && composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
