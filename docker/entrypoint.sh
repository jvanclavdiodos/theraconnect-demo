#!/bin/sh
# Container boot sequence (Railway + docker-compose).
#
# Kept as a script (not an inline Dockerfile CMD) so the DB-readiness probe and
# control flow are plain POSIX sh — no nested `sh -c "sh -c \"...\""` quoting to
# get wrong.
#
# The DB readiness probe is shared with the queue-worker and scheduler services
# via docker/wait-for-db.sh. A bare PDO connect (NOT `php artisan db:show`)
# only checks that the database is reachable and accepting auth — which is all
# we need here; `db:show` needs the absent `intl` extension and fails against a
# non-empty database.
set -e

php artisan storage:link --force

# FCM service-account credentials. Railway has no file uploads, so the JSON is
# supplied base64-encoded via FCM_CREDENTIALS_B64 and written to disk at boot.
# Set FCM_CREDENTIALS to this same path (default below). Never commit the JSON.
if [ -n "${FCM_CREDENTIALS_B64:-}" ]; then
    FCM_CRED_PATH="${FCM_CREDENTIALS:-/var/www/storage/app/private/firebase-credentials.json}"
    mkdir -p "$(dirname "$FCM_CRED_PATH")"
    echo "$FCM_CREDENTIALS_B64" | base64 -d > "$FCM_CRED_PATH"
    chmod 600 "$FCM_CRED_PATH"
    echo "FCM credentials written to ${FCM_CRED_PATH}"
fi

sh /var/www/docker/wait-for-db.sh

php artisan migrate --force
php artisan db:seed --force

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
