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

echo "Esperando a PostgreSQL en ${DB_HOST}:${DB_PORT}..."
until php -r "try { new PDO('pgsql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT').';dbname='.getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); exit(0); } catch (Throwable \$e) { fwrite(STDERR, \$e->getMessage().PHP_EOL); exit(1); }"; do
  sleep 2
done

php artisan package:discover --ansi
php artisan migrate --force

COUNT=$(php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo Illuminate\Support\Facades\DB::table("users")->count();')

if [ "$COUNT" = "0" ]; then
  echo "Base vacía: cargando datos de prueba..."
  if ! php artisan db:seed --force; then
    echo "El seed falló. La API arranca con las migraciones aplicadas."
  fi
fi

echo "API lista en http://localhost:8000"
exec php artisan serve --host=0.0.0.0 --port=8000
