#!/bin/sh
# Prepara la aplicación al iniciar el contenedor y levanta el servidor web.
# Local (docker compose): SQLite con datos de demostración nuevos en cada inicio.
# Producción (Railway): MySQL persistente; las variables de entorno del servicio mandan sobre .env.
set -e

[ -f .env ] || cp .env.example .env

# En producción APP_KEY llega como variable de entorno; si se generara en cada inicio,
# se invalidarían las sesiones y los datos cifrados.
if [ -z "${APP_KEY:-}" ] && ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force --no-interaction
fi

php artisan package:discover --ansi

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
  touch database/database.sqlite
fi

if [ "${DEMO_RESET:-true}" = "true" ]; then
  # Demo local: base limpia con datos ficticios en cada inicio
  php artisan migrate:fresh --seed --force --no-interaction
else
  # Producción: solo aplica las migraciones nuevas, nunca borra datos
  php artisan migrate --force --no-interaction
  if [ "${SEED_DEMO:-false}" = "true" ]; then
    php artisan inventario:sembrar-demo
  fi
fi

if [ "${APP_ENV:-local}" = "production" ]; then
  php artisan config:cache
  php artisan view:cache
fi

# Tareas programadas (recálculo diario del punto de reorden, HU10)
if [ "${RUN_SCHEDULER:-false}" = "true" ]; then
  php artisan schedule:work >> storage/logs/scheduler.log 2>&1 &
fi

PORT="${PORT:-8000}"
echo "Inventario El Portal escuchando en el puerto ${PORT}"
exec php artisan serve --host=0.0.0.0 --port="${PORT}"
