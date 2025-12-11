# Production Dockerfile for SVG Converter (Laravel + Octane via FrankenPHP)
# Multi-stage build to keep runtime image small.

# --- Build stage: install PHP dependencies
FROM php:8.4-cli AS vendor
WORKDIR /app

# Install required tools for Composer (dist archives) and enable php-zip
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       git unzip zip ca-certificates libzip-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure zip \
    && docker-php-ext-install zip

# Install Composer by copying the binary from the official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_CACHE_DIR=/tmp/composer-cache

COPY composer.json composer.lock ./
# Prefer dist, no dev, optimize autoloader; avoid running scripts during build
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts --no-progress

# --- Runtime stage: FrankenPHP with PHP 8.4+
# See: https://github.com/dunglas/frankenphp
FROM dunglas/frankenphp:1-php8.4 AS runtime

# Enable required PHP extensions (Imagick optional; present in this image variants when available)
# Octane requires signal handling; enable pcntl. Add imagick if needed for real conversions.
RUN install-php-extensions pcntl redis
RUN install-php-extensions imagick

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

# Ensure no stale Laravel caches are present at build time. Runtime caching happens in entrypoint.
RUN rm -f bootstrap/cache/*.php || true

# Copy entrypoint script and make it executable
COPY scripts/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Expose FrankenPHP default port
EXPOSE 80

# Healthcheck: basic ping endpoint
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD curl -fsS http://127.0.0.1/api/ping || exit 1

# Start via entrypoint which will run migrations, then boot Octane/FrankenPHP
ENTRYPOINT ["/entrypoint.sh"]
