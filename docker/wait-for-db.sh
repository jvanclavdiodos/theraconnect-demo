#!/bin/sh
# Lightweight DB readiness probe shared by entrypoint.sh, queue-worker, and
# scheduler. Uses a bare PDO connect — NOT `php artisan db:show`, which needs
# the `intl` extension (absent from this image) and fails against a non-empty
# database. Fails fast so the calling service exits rather than spinning forever.
set -e

MAX_ATTEMPTS="${WAIT_DB_MAX_ATTEMPTS:-30}"
ATTEMPTS=0

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
        echo "Database not reachable after ${MAX_ATTEMPTS} attempts (~60s); aborting."
        exit 1
    fi
    echo "Waiting for database... (${ATTEMPTS}/${MAX_ATTEMPTS})"
    sleep 2
done

echo "Database is up."
