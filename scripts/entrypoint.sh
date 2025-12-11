#!/usr/bin/env sh
set -euo pipefail

# Echo environment
echo "[entrypoint] APP_ENV=${APP_ENV:-} APP_DEBUG=${APP_DEBUG:-}"

cd /app

# Ensure storage permissions (in case of mounted volumes)
# Create required directories explicitly (no brace expansion in POSIX sh)
mkdir -p storage/framework/cache || true
mkdir -p storage/framework/cache/data || true
mkdir -p storage/framework/sessions || true
mkdir -p storage/framework/views || true
mkdir -p storage/framework/octane || true
mkdir -p storage/logs || true
mkdir -p bootstrap/cache || true
# Ensure a default log file exists
: > storage/logs/laravel.log || true
# Fix ownership/permissions (volumes may override image-time chown)
chown -R www-data:www-data storage bootstrap/cache database || true
chmod -R ug+rwX storage bootstrap/cache database || true
# Diagnostics & fail-fast if not writable
for p in storage storage/framework storage/framework/cache storage/framework/octane bootstrap/cache database; do
  if [ ! -e "$p" ]; then
    echo "[entrypoint] ERROR: path missing: $p"; ls -ld "$p" 2>/dev/null || true; exit 1;
  fi
  if [ ! -w "$p" ]; then
    echo "[entrypoint] ERROR: path not writable: $p"; ls -ld "$p" || true; id || true; exit 1;
  fi
done

# Ensure app key exists (support images without .env)
if [ -z "${APP_KEY:-}" ]; then
  if [ -f ".env" ]; then
    php artisan key:generate --force || true
  else
    # Generate a runtime key and export it for this process
    APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
    export APP_KEY
    echo "[entrypoint] Generated runtime APP_KEY"
  fi
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

# Clear any pre-existing cached manifests that may reference dev packages
rm -f bootstrap/cache/*.php || true

# Optimize caches
php artisan config:cache || true
php artisan route:cache || true
if [ "${ENABLE_VIEW_CACHE:-false}" = "true" ] && [ -d "resources/views" ]; then
  php artisan view:cache || true
else
  echo "[entrypoint] Skipping view:cache (ENABLE_VIEW_CACHE!=true or no resources/views)"
fi

# Run database migrations (no-interactive)
php artisan migrate --force || true

# Optional: queue work can be handled separately if using external worker
# Start the Octane server with FrankenPHP
OCTANE_WORKERS=${OCTANE_WORKERS:-4}
OCTANE_MAX_REQUESTS=${OCTANE_MAX_REQUESTS:-250}
OCTANE_ADMIN_PORT=${OCTANE_ADMIN_PORT:-2020}

exec php artisan octane:start \
  --server=frankenphp \
  --host=0.0.0.0 \
  --port=80 \
  --admin-port="$OCTANE_ADMIN_PORT" \
  --workers="$OCTANE_WORKERS" \
  --max-requests="$OCTANE_MAX_REQUESTS"
