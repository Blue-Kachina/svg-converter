# Project Development Guidelines — SVG Converter (Laravel + Octane)

This document summarizes project-specific practices for building, running, testing, and extending this service. It targets experienced Laravel developers and focuses on the particulars of this codebase.

## 1) Build and Configuration

- Runtime options
  - Laravel Octane via FrankenPHP is supported. Prefer Octane in local dev when working on performance or long‑running worker behavior.
  - Laravel Sail (Docker) is available with Redis; an alias for `sail` commands is assumed in this repo.

- Quick start (Docker/Sail)
  - Start the app and Redis using Sail’s service: if you have the Sail alias, most commands are prefixed with `sail`.
  - Common ports are configured in `compose.yaml`:
    - `APP_PORT` (default 80)
    - `VITE_PORT` (default 5173)
  - Redis is available at `redis:6379` inside the container.

- Environment
  - Copy env: `cp .env.example .env` (first run only).
  - Generate app key: `sail artisan key:generate`.
  - For tests, `phpunit.xml` forces `DB_CONNECTION=sqlite` and `DB_DATABASE=database/database.sqlite`.
    - Ensure the SQLite file exists: `sail php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"`
  - Queue: test env uses `QUEUE_CONNECTION=sync` via `phpunit.xml`.

- Composer scripts (local, not inside Sail) are declared in `composer.json`:
  - `composer dev` runs PHP server, queue listener, pail, and Vite concurrently (requires Node + `npx concurrently`).
  - `composer octane` starts Octane (FrankenPHP). In Sail, prefer `sail artisan octane:start --server=frankenphp`.
  - `composer test` clears config and runs the test suite.

- Octane specifics
  - Expect persistent worker lifecycle. Avoid static/global state. Services should be stateless or reset per request.
  - Verify that anything that caches config or state during a request is safe across requests.

- Logging
  - Logs are in `storage/logs/*.log`. Check for Octane worker messages and conversion errors.

## 2) Testing

- Runner
  - With Sail alias: `sail artisan test` is the canonical runner.
  - To run a specific file: `sail artisan test tests/Unit/SvgInputValidatorTest.php`.
  - To filter a test case/method: `sail artisan test --filter SvgInputValidatorTest` or `--filter test_malicious_svg_is_detected_and_sanitized`.
  - To run a suite: `sail artisan test --testsuite=Unit` or `--testsuite=Feature`.

- Test database
  - `phpunit.xml` sets `DB_CONNECTION=sqlite` and `DB_DATABASE=database/database.sqlite`.
  - Ensure `database/database.sqlite` exists before running tests (see command above). No migrations are strictly required for the current tests.

- Imagick and conversion tests
  - Some unit tests for conversion expect `Imagick`. Those tests include a guard to skip when `Imagick` is missing.
  - However, in this codebase certain services also call framework helpers like `config()` in constructors or methods. If you instantiate them in a plain PHPUnit case, you must boot the Laravel container; otherwise you will see `BindingResolutionException: Target class [config] does not exist.`
  - Two options when writing tests:
    1) Pure unit test (no container): Don’t invoke Laravel helpers like `config()`, or refactor to inject dependencies (e.g., pass in config values explicitly).
    2) Application-aware test: Extend `Tests\TestCase` (not `PHPUnit\Framework\TestCase`) to boot the kernel, then `config()` et al. work. Use this if you need the container, facades, or `config()`.

- Demo test (verified)
  - Example minimal test that avoids Laravel boot and passes in isolation:
    ```php
    <?php
    namespace Tests\Unit;
    use PHPUnit\Framework\TestCase;
    class DemoGuidelinesTest extends TestCase
    {
        public function test_basic_math_works(): void
        {
            $this->assertSame(4, 2 + 2);
        }
    }
    ```
  - Run just this test:
    ```bash
    sail artisan test --filter DemoGuidelinesTest
    ```
  - Expected output: 1 test passed.
  - Note: This file is for demonstration only; do not commit such placeholder tests.

- Adding new tests
  - Unit tests that use Laravel features should extend `Tests\TestCase`. This base class lives at `tests/TestCase.php` and boots the application.
  - For SVG inputs, reuse `Tests\Support\InteractsWithSvg` which provides helpers like `makeSvg()` and `makeMaliciousSvg()`.
  - Prefer explicit dependency injection for services to reduce the need for the container in unit tests.
  - If you need Redis, prefer integration tests that run under Sail.

- Useful commands
  - Verbose: `sail artisan test -v` or `-vvv` for more detail.
  - Stop on failure: `sail artisan test --stop-on-failure`.
  - Coverage (if Xdebug configured): `sail artisan test --coverage`.

## 3) Local run modes

- Simple PHP server
  - `sail artisan serve` or from host `php artisan serve` if running locally without Docker.

- Octane (FrankenPHP)
  - With Sail: `sail artisan octane:start --server=frankenphp --workers=1 --max-requests=250`.
  - When using Octane, re-check any code relying on per-request initialization.

- API endpoints of interest (see `routes/api.php`)
  - `POST /api/convert` — primary conversion endpoint (see README for schema/options).
  - `GET /api/health`, `GET /api/status` — service diagnostics.
  - `GET /api/ping` — basic responsiveness check.

## 4) Development notes and conventions

- Code style
  - Laravel Pint is available: run `sail composer exec -- vendor/bin/pint` or from host `./vendor/bin/pint`.
  - Match existing formatting and docblock style. Keep services stateless where possible.

- Error handling
  - Use domain exceptions (e.g., `SvgConversionException`) in conversion paths.
  - Map exceptions to proper HTTP responses in controllers.

- Security
  - Be mindful of SVG sanitization. Use `SvgInputValidator` and test malicious payloads via `InteractsWithSvg::makeMaliciousSvg()`.
  - Enforce sensible limits (dimensions, density, size) before converting.

- Observability
  - Use Monolog structured logs for failures and performance metrics. Consider adding conversion duration, output size, and error context.

- Queues
  - Test environment uses `sync`. For background work experiments, enable Redis in Sail and switch queue config accordingly.

## 5) Troubleshooting

- `BindingResolutionException: Target class [config] does not exist.`
  - You’re instantiating a class that uses Laravel helpers without booting the app. Extend `Tests\TestCase` or refactor to inject config.

- Feature test fails with PDO MySQL constants error in environments without ext/pdo_mysql
  - Ensure your test suite sticks to SQLite (as configured by `phpunit.xml`) and that no MySQL-specific code is triggered. Booting the full app in feature tests may try to resolve default DB driver; keep `.env.testing` aligned or rely on `phpunit.xml` overrides.

- Imagick not available
  - Conversion tests should gracefully skip when `Imagick` is missing. If not, adjust tests to assert skip conditions early or provide a mock converter.

---

If you change any of the above (e.g., switch default DB, add new runtime requirements, or introduce container usage in unit-tested classes), update this guideline accordingly.
