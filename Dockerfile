# Pinned to a patch version for production reproducibility. Bumping the minor
# (8.2 -> 8.3) requires verifying extension compatibility.
FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json /app/
RUN npm install --no-audit --no-fund

COPY vite.config.js /app/
COPY resources /app/resources
RUN npm run build

FROM php:8.2.25-cli-alpine

RUN apk add --no-cache \
    curl \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    zip \
    unzip \
    mysql-client \
    fcgi

RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    zip \
    gd \
    intl \
    pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Ship a php.ini that raises upload limits above the app's in-app max rules
# (so FormRequest surfaces the friendly "must not be greater than X MB" message
# rather than the opaque PostTooLargeException 413 page), suppresses the
# X-Powered-By header at the SAPI level, and routes PHP errors to stderr so
# PaaS log drains capture them.
COPY docker/php.ini /usr/local/etc/php/conf.d/theraconnect.ini

WORKDIR /var/www

# Layer cache: copy only manifest files first, so dependency install is cached
# and is NOT invalidated by every source-code change.
#
# --no-scripts is REQUIRED here: composer's post-autoload-dump hook runs
# `php artisan package:discover`, but at this layer only composer.json/.lock
# exist — `artisan` and app/ haven't been copied yet, so the hook would fail
# and abort the build. We defer the scripts until after the full source copy.
COPY composer.json composer.lock /var/www/
RUN composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader --no-scripts

# Now copy the rest of the application code.
COPY . /var/www
COPY --from=frontend /app/public/build /var/www/public/build

# Regenerate the optimized autoloader now that artisan + app/ are present, which
# also runs the deferred post-autoload-dump scripts (php artisan package:discover).
RUN composer dump-autoload --no-interaction --optimize --no-dev

# Run the app as a non-root user (www-data). Container is internet-facing on
# Railway, so maximum-blast-radius root execution is unacceptable even for a
# pilot. The DB bootstrap steps below (storage:link, migrate, seed) all work
# fine as www-data once /var/www is chowned to it.
RUN chown -R www-data:www-data /var/www
USER www-data

EXPOSE 8080

# Boot flow lives in docker/entrypoint.sh (plain POSIX sh — easier to read and
# free of nested-shell escaping). It: storage:link -> wait for the DB via a
# lightweight PDO connect (bounded, ~60s) -> migrate --force -> db:seed --force
# (idempotent) -> serve on $PORT. The PDO probe replaces `php artisan db:show`,
# which needs the absent `intl` extension and failed against a non-empty DB.
CMD ["sh", "/var/www/docker/entrypoint.sh"]
