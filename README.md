# SVG Converter
## Description
This is a Laravel-based microservice.
It can accept SVGs via its API, and it returns a base64-encoded-PNG string
It's currently dev-only, and is set up using Sail.  Includes redis container

## Basic Setup (Dev via Sail)
- Checkout from git, then cd to that directory
- Create yourself a `.env` based on the `.env.example` and configure appropriately (example has sensible defaults)
- `vendor/bin/sail up -d`
- `vendor/bin/sail composer octane`
- From another terminal: `vendor/bin/sail composer octane-queue`
At this point in time, you should be up and running.

## Production (Docker + FrankenPHP/Octane)
- Build the image:
  ```bash
  docker build -t svg-converter:latest -f Dockerfile .
  ```
- Run with docker compose (production):
  ```bash
  APP_PORT=8080 docker compose -f docker-compose.prod.yml up -d --build
  ```
- Copy `.env.production.example` to your environment-specific file and provide a secure `APP_KEY`.
- See `docs/DEPLOYMENT.md` for details and options.

Interactive OpenAPI documentation is available here:

[![Swagger UI](https://img.shields.io/badge/Swagger-UI-85EA2D?style=for-the-badge&logo=swagger)](https://petstore.swagger.io/?url=https://raw.githubusercontent.com/Blue-Kachina/svg-converter/master/docs/openapi.yaml)
