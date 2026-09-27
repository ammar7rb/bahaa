#!/usr/bin/env bash
set -euo pipefail

site=/home/sigma-eg/htdocs/sigma-eg.net
cd "$site"

# Fail instead of installing against an incomplete or incorrect checkout.
test -f .env
test -f storage/oauth-private.key
test -f composer.lock
test -f artisan

php8.3 /usr/local/bin/composer install \
  --no-dev --no-interaction --prefer-dist --optimize-autoloader
php8.3 artisan optimize:clear
php8.3 artisan migrate --force --no-interaction
php8.3 artisan queue:restart
