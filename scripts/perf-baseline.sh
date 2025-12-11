#!/usr/bin/env bash
set -euo pipefail

# Performance baseline runner for the SVG Converter
# Requires: Sail (Docker) or a local PHP env with Laravel installed.
# It will:
#  1) Ensure the sqlite database file exists
#  2) Warm up the app
#  3) Run svg:benchmark and svg:load-test with configurable params
#  4) Save raw outputs to artifacts/
#
# Usage (inside repo):
#   bash scripts/perf-baseline.sh [requests] [concurrency] [iterations]
# Defaults: requests=200 concurrency=20 iterations=100

REQUESTS=${1:-200}
CONCURRENCY=${2:-20}
ITERATIONS=${3:-100}
ARTIFACT_DIR=${ARTIFACT_DIR:-artifacts}
DATE_TAG=$(date +%Y%m%d-%H%M%S)
mkdir -p "$ARTIFACT_DIR"

ensure_sqlite() {
  if command -v sail >/dev/null 2>&1; then
    sail php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
  else
    php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
  fi
}

artisan() {
  if command -v sail >/dev/null 2>&1; then
    sail artisan "$@"
  else
    php artisan "$@"
  fi
}

# Prepare
ensure_sqlite

# Warm up (Octane users may want to start octane first)
artisan config:clear || true
artisan route:clear || true

# Benchmark single-request conversions
echo "Running svg:benchmark --iterations=$ITERATIONS ..."
artisan svg:benchmark --iterations="$ITERATIONS" | tee "$ARTIFACT_DIR/benchmark-$DATE_TAG.txt"

# Load test API endpoint
echo "Running svg:load-test --requests=$REQUESTS --concurrency=$CONCURRENCY ..."
artisan svg:load-test --requests="$REQUESTS" --concurrency="$CONCURRENCY" | tee "$ARTIFACT_DIR/loadtest-$DATE_TAG.txt"

echo "Artifacts written to $ARTIFACT_DIR/"
