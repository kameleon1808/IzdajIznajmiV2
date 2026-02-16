#!/usr/bin/env bash
set -euo pipefail

ENVIRONMENT=${ENVIRONMENT:-production}
APP_DIR=${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}
PHP_BIN=${PHP_BIN:-php}
COMPOSER_BIN=${COMPOSER_BIN:-composer}
FRONTEND_BUILD=${FRONTEND_BUILD:-0}
HEALTH_URL=${HEALTH_URL:-http://127.0.0.1/api/v1/health}
APP_VERSION=${APP_VERSION:-$(git -C "$APP_DIR" rev-parse --short HEAD 2>/dev/null || echo dev)}

echo "[deploy] environment=${ENVIRONMENT} app_dir=${APP_DIR} app_version=${APP_VERSION}"

cd "$APP_DIR/backend"

if [[ ! -f .env ]]; then
  echo "backend/.env not found. Copy an env file before deploying." >&2
  exit 1
fi

export APP_VERSION

echo "[deploy] composer install"
"$COMPOSER_BIN" install --no-dev --prefer-dist --optimize-autoloader

echo "[deploy] clear + cache config/routes/views"
"$PHP_BIN" artisan config:clear
"$PHP_BIN" artisan cache:clear

echo "[deploy] database migrations"
"$PHP_BIN" artisan migrate --force

"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

echo "[deploy] restart queues"
"$PHP_BIN" artisan queue:restart || true
if command -v supervisorctl >/dev/null 2>&1; then
  supervisorctl reread || true
  supervisorctl update || true
  supervisorctl restart izdaji-queue:* || true
fi

if [[ "$ENVIRONMENT" == "staging" ]]; then
  echo "[deploy] optional staging warmup: geocode missing"
  "$PHP_BIN" artisan listings:geocode --missing --quiet || true
fi

echo "[deploy] run one-off scheduler tick"
"$PHP_BIN" artisan schedule:run --verbose --no-interaction || true

echo "[deploy] smoke health ${HEALTH_URL}"
if command -v curl >/dev/null 2>&1; then
  curl --fail --silent "${HEALTH_URL}" >/dev/null
else
  echo "curl not found; skipping HTTP smoke check" >&2
fi

cd "$APP_DIR"

if [[ "$FRONTEND_BUILD" == "1" ]]; then
  if [[ -d frontend ]]; then
    echo "[deploy] building frontend (npm ci && npm run build)"
    cd frontend
    npm ci
    npm run build
    cd "$APP_DIR"
  else
    echo "frontend directory not found; skipping frontend build" >&2
  fi
fi

echo "[deploy] done"
