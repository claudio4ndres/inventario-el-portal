# Imagen de demostración del sistema de inventario El Portal (Laravel 13 + PHP 8.4 + SQLite)
FROM php:8.4-cli-bookworm

# pdo_sqlite ya viene en la imagen oficial; se agrega pdo_mysql para usar MySQL 8.4 si se desea
RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip libzip-dev \
 && docker-php-ext-install pdo_mysql zip \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app

# 1) Dependencias primero: Docker reutiliza esta capa mientras composer.json no cambie
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

# 2) Código de la aplicación
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts \
 && chmod +x docker/entrypoint.sh \
 && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

ENV APP_ENV=local \
    DB_CONNECTION=sqlite \
    DEMO_RESET=true

EXPOSE 8000
ENTRYPOINT ["docker/entrypoint.sh"]
