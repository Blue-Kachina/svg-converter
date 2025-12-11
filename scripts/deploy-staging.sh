#!/usr/bin/env bash
set -euo pipefail

# Deploy to staging using docker-compose.staging.yml
# Build and run locally or in CI. Requires Docker.

REGISTRY=${REGISTRY:-}
IMAGE_NAME=${IMAGE_NAME:-svg-converter}
TAG=${TAG:-staging-$(git rev-parse --short HEAD 2>/dev/null || echo latest)}
FULL_IMAGE_REF=${REGISTRY:+$REGISTRY/}$IMAGE_NAME:$TAG
APP_PORT=${APP_PORT:-8081}

echo "Building $FULL_IMAGE_REF ..."
docker build -t "$FULL_IMAGE_REF" -f Dockerfile .

echo "Starting stack on port $APP_PORT ..."
IMAGE="$FULL_IMAGE_REF" APP_PORT="$APP_PORT" docker compose -f docker-compose.staging.yml up -d --build

echo "Waiting for healthcheck ..."
sleep 3
docker compose -f docker-compose.staging.yml ps

echo "Staging URL: http://localhost:$APP_PORT"
echo "Health:     http://localhost:$APP_PORT/api/health"
echo "Status:     http://localhost:$APP_PORT/api/status"
echo "Metrics:    http://localhost:$APP_PORT/api/metrics"
