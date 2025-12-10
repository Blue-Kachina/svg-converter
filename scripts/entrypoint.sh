#!/usr/bin/env sh
set -euo pipefail

# Echo environment
echo "[entrypoint] APP_ENV=${APP_ENV:-} APP_DEBUG=${APP_DEBUG:-}"

cd /app

# Ensure storage permissions (in case of mounted volumes)
mkdir -p storage/framework/{cache,sessions,views} bootstrap/cache || true
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R ug+rwX storage bootstrap/cache || true

# Ensure app key exists
if [ -z "${APP_KEY:-}" ]; then
  php artisan key:generate --force || true
fi

# If using SQLite, ensure database file exists
if [ "${DB_CONNECTION:-}" = "sqlite" ]; then
  DB_PATH="${DB_DATABASE:-database/database.sqlite}"
  if [ ! -f "$DB_PATH" ]; then
    echo "[entrypoint] Creating SQLite database at $DB_PATH"
    mkdir -p "$(dirname "$DB_PATH")"
    touch "$DB_PATH"
  fi
fi

# Optimize caches
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Run database migrations (no-interactive)
php artisan migrate --force || true

# Optional: queue work can be handled separately if using external worker
# Start the Octane server with FrankenPHP
OCTANE_WORKERS=${OCTANE_WORKERS:-4}
OCTANE_MAX_REQUESTS=${OCTANE_MAX_REQUESTS:-250}

exec php artisan octane:start \
  --server=frankenphp \
  --host=0.0.0.0 \
  --port=80 \
  --workers="$OCTANE_WORKERS" \
  --max-requests="$OCTANE_MAX_REQUESTS"
