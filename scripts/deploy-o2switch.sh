#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   ./scripts/deploy-o2switch.sh
#
# Assumes:
# - you are running this on the o2switch server
# - repo is already cloned
# - .env is already configured

PROJECT_DIR="${PROJECT_DIR:-$HOME/apps/famille-platform}"
BRANCH="${BRANCH:-main}"

cd "$PROJECT_DIR"

echo "==> Fetching latest code ($BRANCH)"
git fetch --all --prune

git checkout "$BRANCH"
git pull --ff-only

echo "==> Installing PHP deps"
composer install --no-dev --optimize-autoloader

echo "==> Running migrations"
php artisan migrate --force

echo "==> Clearing caches (safe)"
php artisan optimize:clear || true
rm -f bootstrap/cache/*.php || true
rm -f storage/framework/views/*.php || true

echo "==> Caching config/routes/views"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Done"
