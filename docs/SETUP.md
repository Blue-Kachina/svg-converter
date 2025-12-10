# Setup & Installation Guide

This project is a Laravel-based SVG → PNG conversion microservice. You can run it via Docker (Laravel Sail) or locally. Octane with FrankenPHP is supported.

## Prerequisites
- Docker + Docker Compose (recommended), or PHP 8.4+, Composer, Node 20+ for local runs
- Make sure ports are available:
  - APP_PORT (default 80)
  - VITE_PORT (default 5173)

## Quick Start (Docker/Sail)
1. Clone the repo and enter the directory.
2. Copy env and generate app key:
   ```bash
   cp .env.example .env
   ./vendor/bin/sail artisan key:generate || (composer install && ./vendor/bin/sail artisan key:generate)
   ```
3. Start services (app + Redis):
   ```bash
   ./vendor/bin/sail up -d
   ```
4. Ensure test SQLite database file exists:
   ```bash
   ./vendor/bin/sail php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
   ```
5. Open: http://localhost/api/ping

### Running the Test Suite
```bash
./vendor/bin/sail artisan test
```
- To run a specific file:
  ```bash
  ./vendor/bin/sail artisan test tests/Unit/SvgInputValidatorTest.php
  ```
- SQLite is configured via `phpunit.xml`.

### Octane (FrankenPHP)
Use Octane for performance testing and long-running worker behavior:
```bash
./vendor/bin/sail artisan octane:start --server=frankenphp --workers=1 --max-requests=250
```

## Local (without Docker)
1. Install PHP extensions as needed (e.g., `ext-fileinfo`, `ext-json`). Imagick is optional; conversion tests skip when missing.
2. Install dependencies:
   ```bash
   composer install
   npm install
   ```
3. Copy env and generate key:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
4. Start app:
   ```bash
   php artisan serve
   ```
5. Optional: run queue worker for batch processing:
   ```bash
   php artisan queue:work
   ```

## Useful Commands
- Benchmark conversions:
  ```bash
  ./vendor/bin/sail artisan svg:benchmark --iterations=100
  ```
- Load test API:
  ```bash
  ./vendor/bin/sail artisan svg:load-test --requests=200 --concurrency=20
  ```

## Endpoints
- POST /api/convert — convert SVG to PNG (JSON default, supports PNG and base64)
- POST /api/batch-convert — submit multiple conversions
- GET /api/batch/{id} — poll batch status
- GET /api/health, /api/status, /api/ping — diagnostics
- GET /api/metrics — Prometheus metrics (if enabled)

See OpenAPI spec at `docs/openapi.yaml`. You can preview with Redocly:
```bash
npx @redocly/cli preview-docs docs/openapi.yaml
```
