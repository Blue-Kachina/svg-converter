# Deployment Guide (Production)

This document explains how to build and run the SVG Converter in a production-like container using FrankenPHP + Laravel Octane.

## Overview
- Runtime: Laravel Octane via FrankenPHP
- Containerization: Docker (multi-stage build)
- Healthcheck: `GET /api/ping`
- Metrics: `GET /api/metrics` (configurable; disabled by default in production example)

## Build the Image

```bash
# From the repository root
docker build -t svg-converter:latest -f Dockerfile .
```

- The Dockerfile uses a Composer build stage to install PHP dependencies without dev packages.
- Runtime image is based on `dunglas/frankenphp` with PHP 8.4.

## Run with Docker Compose (Production)

A convenience compose file is provided:

```bash
APP_PORT=8080 docker compose -f docker-compose.prod.yml up -d --build
```

- The app will be available at http://localhost:8080
- Data is persisted via volumes: `app_storage` and `app_database`.
- Defaults to SQLite; override database environment variables for MySQL/Postgres.

## Environment Configuration

Use `.env.production.example` as a template. Key variables:

- `APP_ENV=production`, `APP_DEBUG=false`
- Octane/FrankenPHP: `OCTANE_SERVER`, `OCTANE_WORKERS`, `OCTANE_MAX_REQUESTS`
- Database: `DB_CONNECTION`, `DB_DATABASE` (for SQLite), or driver-specific variables for other DBs
- Metrics toggles: `METRICS_ENABLED`, `METRICS_EXPOSE`, optional `METRICS_BASIC_AUTH`
- SVG limits: `SVG_MAX_BYTES`, `SVG_MAX_PNG_BYTES`, `SVG_OVERSIZE_STRATEGY`

Provide a real `APP_KEY` in production or let the entrypoint generate one on first start.

## Entrypoint Behavior

The image uses `scripts/entrypoint.sh` which performs:
- Storage/bootstrap permissions fix
- Optional `APP_KEY` generation if missing
- SQLite file creation if `DB_CONNECTION=sqlite`
- Cache optimization (`config:cache`, `route:cache`, `view:cache`)
- Database migrations: `php artisan migrate --force`
- Starts Octane via FrankenPHP on port 80

## Health and Logs

- Healthcheck: `GET /api/ping`
- Logs are written to stderr by default (`LOG_CHANNEL=stderr`); collect them using your container platform.

## Example Registry Build and Run

```bash
# Build and tag image (replace registry)
REGISTRY=ghcr.io/your-org IMAGE_NAME=svg-converter TAG=$(git rev-parse --short HEAD)
FULL_IMAGE_REF=$REGISTRY/$IMAGE_NAME:$TAG

docker build -t "$FULL_IMAGE_REF" -f Dockerfile .
# docker push "$FULL_IMAGE_REF"

# Run using compose with custom image reference
APP_PORT=8080 IMAGE=$FULL_IMAGE_REF docker compose -f docker-compose.prod.yml up -d --build
```

See `scripts/deploy-example.sh` for a minimal example script.

## Switching to Redis / External Queue

For higher throughput or background jobs, enable Redis and switch the queue driver:
- Uncomment the `redis` service in `docker-compose.prod.yml`
- Set `QUEUE_CONNECTION=redis` and configure `REDIS_HOST=redis`
- Run a separate queue worker container (not included here) if using async processing

## Security Notes

- Keep `APP_DEBUG=false` in production.
- Consider enabling metrics endpoint only behind authentication or in internal networks.
- If exposing raw PNG responses, ensure proper limits and sanitization are in place (already enforced in this project).
