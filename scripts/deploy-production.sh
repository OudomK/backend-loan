#!/usr/bin/env bash
set -euo pipefail

SKIP_NPM="${SKIP_NPM:-0}"
SKIP_MIGRATE="${SKIP_MIGRATE:-0}"

step() {
  echo "==> $1"
}

echo "Starting production deploy..."

if [[ ! -f ".env" ]]; then
  echo ".env not found. Copy from .env.production.example and fill real values first."
  exit 1
fi

APP_IS_DOWN=0
cleanup() {
  if [[ "$APP_IS_DOWN" == "1" ]]; then
    php artisan up || true
  fi
}
trap cleanup EXIT

step "Composer install (no-dev, optimized)"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

if [[ "$SKIP_NPM" != "1" ]]; then
  step "NPM install"
  npm ci
  step "Build frontend assets"
  npm run build
fi

step "Put app in maintenance mode"
php artisan down
APP_IS_DOWN=1

if [[ "$SKIP_MIGRATE" != "1" ]]; then
  step "Run migrations (force)"
  php artisan migrate --force
fi

step "Ensure storage symlink"
php artisan storage:link
step "Clear old caches"
php artisan optimize:clear
step "Cache config"
php artisan config:cache
step "Cache routes"
php artisan route:cache
step "Cache events"
php artisan event:cache
step "Clear compiled views"
php artisan view:clear
step "Cache views"
php artisan view:cache
step "Bring app up"
php artisan up
APP_IS_DOWN=0

echo "Deploy completed successfully."
