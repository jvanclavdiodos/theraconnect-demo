#!/bin/sh
# Railway queue-worker startup. Database migrations remain owned by the web
# service so simultaneous deploys cannot race each other.
set -eu

echo "Starting TheraConnect queue worker..."

sh /var/www/docker/wait-for-db.sh

QUEUE_DRIVER="${QUEUE_CONNECTION:-database}"
case "$QUEUE_DRIVER" in
    sync|null)
        echo "QUEUE_CONNECTION=${QUEUE_DRIVER} cannot be used by a dedicated worker."
        exit 1
        ;;
esac

# Queue jobs send FCM notifications too. Railway supplies the service account
# as base64 because its Variables UI cannot mount a credentials file.
if [ -n "${FCM_CREDENTIALS_B64:-}" ]; then
    FCM_CRED_PATH="${FCM_CREDENTIALS:-/var/www/storage/app/private/firebase-credentials.json}"
    mkdir -p "$(dirname "$FCM_CRED_PATH")"
    echo "$FCM_CREDENTIALS_B64" | base64 -d > "$FCM_CRED_PATH"
    chmod 600 "$FCM_CRED_PATH"
    echo "FCM credentials prepared for queued push delivery."
else
    echo "WARNING: FCM_CREDENTIALS_B64 is not set; queued push notifications cannot authenticate."
fi

if [ -z "${FCM_PROJECT_ID:-}" ]; then
    echo "WARNING: FCM_PROJECT_ID is not set; queued push notifications will be skipped."
fi

echo "Queue driver: ${QUEUE_DRIVER}; starting long-running worker."

# Do not use --max-time here. Railway's ON_FAILURE policy does not restart a
# worker that exits successfully after a time limit, which previously left the
# service at 0/1 replicas every hour.
exec php artisan queue:work \
    --verbose \
    --tries=3 \
    --sleep=1 \
    --backoff=60,300,600 \
    --timeout="${QUEUE_JOB_TIMEOUT:-60}"
