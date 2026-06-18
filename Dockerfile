FROM php:8.2-cli-alpine
#
# NOTE: pin to a specific patch (or image digest) for production reproducibility:
#   docker pull php:8.2.25-cli-alpine@sha256:<digest>
# The docker-compose and tests currently exercise the major.minor tag.

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

# Probes the public health endpoint every 30s. /api/v1/health returns 200 only
# when Laravel is fully booted and routes are wired, so it's a better signal
# than Railway's default TCP-port check.
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS "http://127.0.0.1:${PORT:-8080}/api/v1/health" || exit 1

# Boot flow:
#   1. storage:link  — symlink public/storage → storage/app/public
#   2. wait-for-db   — block until MySQL answers, then continue. Without this,
#      on first deploy `migrate` runs before MySQL is ready and the container
#      exits early — Railway may then exhaust its retry budget and leave the
#      service down.
#   3. migrate       — schema, fail-fast (no || true)
#   4. db:seed       — idempotent (DemoSeeder early-returns if admin user
#      already exists), no longer swallowed with `|| true`. A genuine seeder
#      failure now aborts the boot instead of leaving a half-seeded DB.
#   5. serve         — php artisan serve on $PORT (or 8080 locally)
CMD sh -c "\
    php artisan storage:link --force && \
    ATTEMPTS=0; MAX_ATTEMPTS=30; \
    until php artisan db:show > /dev/null 2>&1; do \
        ATTEMPTS=\$((ATTEMPTS + 1)); \
        if [ \"\$ATTEMPTS\" -ge \"\$MAX_ATTEMPTS\" ]; then \
            echo \"Database not reachable after \$MAX_ATTEMPTS attempts (~60s); aborting boot.\"; \
            exit 1; \
        fi; \
        echo \"Waiting for database... (\$ATTEMPTS/\$MAX_ATTEMPTS)\"; \
        sleep 2; \
    done && \
    php artisan migrate --force && \
    php artisan db:seed --force && \
    php artisan serve --host=0.0.0.0 --port=${PORT:-8080}"
