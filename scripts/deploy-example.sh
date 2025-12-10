#!/usr/bin/env bash
set -euo pipefail

# Example deployment script for building and running the production image locally or in CI.
# Adjust REGISTRY/IMAGE/TAG for your environment.

REGISTRY=${REGISTRY:-}
IMAGE_NAME=${IMAGE_NAME:-svg-converter}
TAG=${TAG:-$(git rev-parse --short HEAD 2>/dev/null || echo latest)}
FULL_IMAGE_REF=${REGISTRY:+$REGISTRY/}$IMAGE_NAME:$TAG

echo "Building $FULL_IMAGE_REF ..."
docker build -t "$FULL_IMAGE_REF" -f Dockerfile .

echo "Run with docker compose (production):"
echo "  APP_PORT=8080 IMAGE=$FULL_IMAGE_REF docker compose -f docker-compose.prod.yml up -d --build"

# If pushing to a registry, uncomment the following:
# echo "Pushing $FULL_IMAGE_REF ..."
# docker push "$FULL_IMAGE_REF"
