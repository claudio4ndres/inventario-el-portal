#!/bin/sh
# Prepara la aplicación al iniciar el contenedor y levanta el servidor en el puerto 8000.
set -e

[ -f .env ] || cp .env.example .env
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force --no-interaction

php artisan package:discover --ansi

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
  touch database/database.sqlite
fi

if [ "${DEMO_RESET:-true}" = "true" ]; then
  # Demo: base de datos limpia con datos ficticios en cada inicio
  php artisan migrate:fresh --seed --force --no-interaction
else
  php artisan migrate --force --no-interaction
fi

echo "Inventario El Portal listo en http://localhost:8000 (admin@elportal.test / ElPortal2026)"
exec php artisan serve --host=0.0.0.0 --port=8000
