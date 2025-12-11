#!/usr/bin/env bash
set -euo pipefail

# Production deployment helper using docker-compose.prod.yml
# Build the image and run with production settings.

REGISTRY=${REGISTRY:-}
IMAGE_NAME=${IMAGE_NAME:-svg-converter}
TAG=${TAG:-$(git rev-parse --short HEAD 2>/dev/null || echo latest)}
FULL_IMAGE_REF=${REGISTRY:+$REGISTRY/}$IMAGE_NAME:$TAG
APP_PORT=${APP_PORT:-8080}

echo "Building $FULL_IMAGE_REF ..."
docker build -t "$FULL_IMAGE_REF" -f Dockerfile .

echo "Run with docker compose (production):"
echo "  APP_PORT=$APP_PORT IMAGE=$FULL_IMAGE_REF docker compose -f docker-compose.prod.yml up -d --build"

echo "After starting, verify health endpoints:"
echo "  curl -fsS http://localhost:$APP_PORT/api/ping && echo OK"
echo "  curl -fsS http://localhost:$APP_PORT/api/health"
