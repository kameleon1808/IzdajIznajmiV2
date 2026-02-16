#!/usr/bin/env bash
set -euo pipefail

cd /app

if [ ! -f .env ]; then
  cp .env.example .env
fi

wait_for_vendor_autoload() {
  local timeout="${WAIT_FOR_VENDOR_TIMEOUT_SECONDS:-3600}"
  local elapsed=0
  local lock_file="vendor/.composer-installing"

  vendor_ready() {
    [ -f vendor/autoload.php ] || return 1
    [ -f "$lock_file" ] && return 1
    php -r "require '/app/vendor/autoload.php';" >/dev/null 2>&1
  }

  echo "Waiting for backend service to finish Composer install..."
  while ! vendor_ready && [ "$elapsed" -lt "$timeout" ]; do
    sleep 2
    elapsed=$((elapsed + 2))
  done

  vendor_ready
}

if [ "${SKIP_COMPOSER_INSTALL:-}" != "1" ]; then
  if [ ! -f vendor/autoload.php ] || [ -f composer.lock ] && [ composer.lock -nt vendor/autoload.php ]; then
    mkdir -p vendor
    touch vendor/.composer-installing
    trap 'rm -f vendor/.composer-installing' EXIT
    composer install --no-interaction
    rm -f vendor/.composer-installing
    trap - EXIT
  fi
else
  if ! wait_for_vendor_autoload; then
    echo "Timed out waiting for backend Composer install to finish. Check backend logs."
    exit 1
  fi
fi

if grep -q '^APP_KEY=$' .env; then
  php artisan key:generate
fi

if [ ! -f database/database.sqlite ]; then
  mkdir -p database
  touch database/database.sqlite
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R 775 storage bootstrap/cache || true

php artisan storage:link >/dev/null 2>&1 || true

exec "$@"
