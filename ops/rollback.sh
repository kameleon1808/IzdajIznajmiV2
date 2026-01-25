#!/usr/bin/env bash
set -euo pipefail

APP_DIR=${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}
PHP_BIN=${PHP_BIN:-php}
COMPOSER_BIN=${COMPOSER_BIN:-composer}
HEALTH_URL=${HEALTH_URL:-http://127.0.0.1/api/v1/health}
ROLLBACK_REF=${ROLLBACK_REF:-${1:-HEAD~1}}
ALLOW_MIGRATE=${ALLOW_MIGRATE:-0}

echo "[rollback] ref=${ROLLBACK_REF} app_dir=${APP_DIR}"

cd "$APP_DIR"
git fetch --all --prune
git checkout "$ROLLBACK_REF"

cd backend

"$COMPOSER_BIN" install --no-dev --prefer-dist --optimize-autoloader

if [[ "$ALLOW_MIGRATE" == "1" ]]; then
  echo "[rollback] running safe migrate (ensure backward compatible!)"
  "$PHP_BIN" artisan migrate --force
else
  echo "[rollback] skipping migrate (set ALLOW_MIGRATE=1 to run)"
fi

"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

"$PHP_BIN" artisan queue:restart || true
if command -v supervisorctl >/dev/null 2>&1; then
  supervisorctl restart izdaji-queue:* || true
fi

if command -v curl >/dev/null 2>&1; then
  curl --fail --silent "${HEALTH_URL}" >/dev/null || {
    echo "[rollback] health check failed" >&2
    exit 1
  }
fi

echo "[rollback] completed"
