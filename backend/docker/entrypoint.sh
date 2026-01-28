#!/usr/bin/env bash
set -euo pipefail

cd /app

if [ ! -f .env ]; then
  cp .env.example .env
fi

if [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction
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
