# Security Policy and Audit Checklist

This document captures our security posture and the steps required to run a security audit before launch or on a regular cadence.

## Supported Versions

This service follows SemVer. Security patches are applied to the latest minor release on `main`. Older lines may receive backports if critical.

## Reporting a Vulnerability

- Preferred: Open a private security advisory or email the maintainers (see `composer.json` authors) with details and reproduction steps.
- Response target: within 48 hours.
- Please do NOT open public issues for undisclosed vulnerabilities.

## Pre‑Launch Security Audit Checklist

Application configuration
- [ ] `APP_ENV=production` and `APP_DEBUG=false`
- [ ] `APP_KEY` is set and kept secret
- [ ] CORS is restricted to trusted origins if exposed beyond internal networks
- [ ] Rate limiting enabled and tuned (see `app/Providers/RouteServiceProvider` if customized)
- [ ] Ensure `config:cache` and `route:cache` are used in builds

Transport & headers
- [ ] TLS termination in front of the container (ingress/proxy) is configured with modern ciphers
- [ ] Add security headers at the edge (e.g., `Content-Security-Policy`, `X-Content-Type-Options=nosniff`); application returns correct `Content-Type`

Authentication/Authorization
- [ ] API endpoints protected as intended (API key or network policy)
- [ ] Metrics endpoint exposure reviewed (`config/metrics.php`: `expose` should be false or behind auth)

SVG handling & limits
- [ ] SVG sanitization enabled; tests cover malicious payloads (see `Tests\Support\InteractsWithSvg`)
- [ ] Input and output size/dimension limits configured (`config/svg.php`)
- [ ] Imagick (if available) policy limits (memory/disk) set at system level where applicable

Secrets & environment
- [ ] No secrets in repo; use environment variables or secret managers
- [ ] Production/staging use distinct keys and tokens

Dependencies
- [ ] Composer audit is clean (or findings triaged)
- [ ] NPM audit (dev tooling) checked if building assets

Containers & runtime
- [ ] Image uses non‑root where feasible; least privileges
- [ ] Healthchecks configured (`/api/ping`)
- [ ] Logs shipped to aggregation; access restricted

## How to run the security audit

Composer dependency audit
```
# From host or in Sail
composer validate --no-check-publish
composer audit -f summary || true  # review output; non-zero indicates vulnerabilities
```

NPM dev tool audit (optional, if building assets)
```
npm ci --prefer-offline
npm audit --audit-level=high || true
```

Static checks (Laravel)
```
# Ensure config is production-ready
php artisan config:cache
php artisan route:cache
```

Runtime verification (staging/prod)
```
# Replace HOST with the environment host
curl -fsS https://HOST/api/health
curl -fsS https://HOST/api/status
# Metrics should be disabled or protected in prod
curl -i https://HOST/api/metrics || true
```

## Ongoing
- Schedule `composer audit` weekly and on each release
- Review logs for `critical` messages from conversion and alerting logic
- Re-run malicious SVG tests before releases
