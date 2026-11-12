#!/bin/sh
set -e

cd /var/www

KEY_FILE=/var/run/secrets/app-key/APP_KEY
if [ -z "${APP_KEY:-}" ]; then
  if [ -s "$KEY_FILE" ]; then
    APP_KEY=$(cat "$KEY_FILE")
  else
    APP_KEY=$(php artisan key:generate --show --no-ansi)
    printf '%s' "$APP_KEY" > "$KEY_FILE"
  fi
  export APP_KEY
fi

mkdir -p /var/www/database
touch /var/www/database/database.sqlite
php artisan package:discover --ansi
exec php artisan serve --host=0.0.0.0 --port=8000
