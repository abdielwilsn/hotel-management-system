FROM php:8.4-cli-alpine AS base

RUN apk add --no-cache \
        bash \
        curl \
        freetype \
        git \
        icu-libs \
        libjpeg-turbo \
        libpng \
        libxml2 \
        libzip \
        oniguruma \
        postgresql-libs \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        curl-dev \
        freetype-dev \
        icu-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libxml2-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        curl \
        gd \
        intl \
        mbstring \
        opcache \
        pdo_pgsql \
        xml \
        zip \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

FROM base AS vendor

COPY . .

RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader

FROM base AS runtime

WORKDIR /var/www/html

COPY --from=vendor /var/www/html /var/www/html
# public/build is built locally before flyctl deploy and included in the Docker context
COPY public/build /var/www/html/public/build

RUN mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && php artisan storage:link || true

EXPOSE 8080

CMD ["sh", "-lc", "mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache && php artisan migrate --force && exec php artisan serve --host=0.0.0.0 --port=8080"]
