#!/usr/bin/env bash
set -euo pipefail

BASE=${BASE:-http://localhost:8080}

check() {
  local path=$1
  echo -n "Checking $BASE$path ... "
  if curl -fsS "$BASE$path" >/dev/null; then
    echo OK
  else
    echo FAIL && exit 1
  fi
}

check /api/ping
check /api/health
check /api/status

# Metrics may not be exposed in prod; don't fail if unavailable
if curl -fsS -o /dev/null -w "%{http_code}" "$BASE/api/metrics" | grep -qE '^(200|401|403)$'; then
  echo "Metrics endpoint reachable (or appropriately protected)."
else
  echo "Metrics endpoint not reachable (this may be expected if disabled)."
fi
