# Production Dockerfile for SVG Converter (Laravel + Octane via FrankenPHP)
# Multi-stage build to keep runtime image small.

# --- Build stage: install PHP dependencies
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# Prefer dist, no dev, optimize autoloader
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# --- Runtime stage: FrankenPHP with PHP 8.4+
# See: https://github.com/dunglas/frankenphp
FROM dunglas/frankenphp:latest-php8.4 AS runtime

# Enable required PHP extensions (Imagick optional; present in this image variants when available)
# If you need additional extensions, uncomment and adjust.
# RUN install-php-extensions imagick

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    OCTANE_SERVER=frankenphp \
    OCTANE_WORKERS=4 \
    OCTANE_MAX_REQUESTS=250 \
    PHP_OPCACHE_ENABLE=1 \
    PHP_OPCACHE_ENABLE_CLI=1 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0

# Create working directory
WORKDIR /app

# Copy application files
COPY . /app

# Copy vendor from build stage
COPY --from=vendor /app/vendor /app/vendor

# Ensure storage and bootstrap cache are writable
RUN mkdir -p storage/framework/{cache,sessions,views} \
    && mkdir -p bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

# Optimize Laravel caches (config, routes, events). These commands are safe even if some caches are empty.
RUN php artisan key:generate --force \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache || true

# Copy entrypoint script and make it executable
COPY scripts/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Expose FrankenPHP default port
EXPOSE 80

# Healthcheck: basic ping endpoint
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD curl -fsS http://127.0.0.1/api/ping || exit 1

# Start via entrypoint which will run migrations, then boot Octane/FrankenPHP
ENTRYPOINT ["/entrypoint.sh"]
