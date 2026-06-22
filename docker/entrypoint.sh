#!/bin/sh
# Container boot sequence (Railway + docker-compose).
#
# Kept as a script (not an inline Dockerfile CMD) so the DB-readiness probe and
# control flow are plain POSIX sh — no nested `sh -c "sh -c \"...\""` quoting to
# get wrong.
#
# Readiness probe: a lightweight PDO connection, NOT `php artisan db:show`.
# db:show formats table sizes via Number::format(), which requires the `intl`
# PHP extension (absent from this image). Against a NON-EMPTY database it throws
# on every call, so the old `until php artisan db:show` loop spun forever and
# the container never reached `serve`. A bare PDO connect only checks that the
# database is reachable and accepting auth — which is all we need here.
set -e

php artisan storage:link --force || true

echo "Waiting for database connection..."
ATTEMPTS=0
MAX_ATTEMPTS=30
until php -r '
    $h = getenv("DB_HOST") ?: "127.0.0.1";
    $p = getenv("DB_PORT") ?: "3306";
    $d = getenv("DB_DATABASE") ?: "";
    $u = getenv("DB_USERNAME") ?: "root";
    $w = getenv("DB_PASSWORD") ?: "";
    try {
        new PDO("mysql:host={$h};port={$p};dbname={$d}", $u, $w, [PDO::ATTR_TIMEOUT => 3]);
    } catch (Throwable $e) {
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
' 2>/dev/null; do
    ATTEMPTS=$((ATTEMPTS + 1))
    if [ "$ATTEMPTS" -ge "$MAX_ATTEMPTS" ]; then
        echo "Database not reachable after ${MAX_ATTEMPTS} attempts (~60s); aborting boot."
        exit 1
    fi
    echo "Waiting for database... (${ATTEMPTS}/${MAX_ATTEMPTS})"
    sleep 2
done
echo "Database is up."

php artisan migrate --force
php artisan db:seed --force

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
