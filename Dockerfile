FROM php:8.2-cli-alpine

RUN apk add --no-cache \
    curl \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    zip \
    unzip \
    mysql-client \
    fcgi

RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    zip \
    gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . /var/www

RUN composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader

EXPOSE 8080

CMD sh -c "php artisan storage:link --force && php artisan migrate --force && php artisan db:seed --force && php artisan serve --host=0.0.0.0 --port=${PORT:-8080}"
