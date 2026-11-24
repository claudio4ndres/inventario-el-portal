# Manual técnico — Inventario El Portal

Este manual explica cómo instalar, desplegar, operar y mantener el sistema. Está escrito para quien se haga cargo de él después del proyecto. El uso de las pantallas está en el [manual de usuario](manual/manual-usuario.pdf).

| | |
|---|---|
| Versión del sistema | `v1.0.1` (código en producción desde el 16-11-2026) |
| Repositorio | <https://github.com/claudio4ndres/inventario-el-portal> |
| Producción | <https://web-production-b82c44.up.railway.app> |
| Autor | Claudio Figueroa Arias, 2026 |

## 1. Arquitectura

La aplicación es un monolito **Laravel 13** con **PHP 8.4**. Las pantallas se generan en el servidor con Blade y Bootstrap 5, y no hay una API separada. Las reglas de negocio están en una capa de servicios, y los controladores solo validan la solicitud y delegan.

| Capa | Ubicación |
|------|-----------|
| Rutas y control de acceso por rol | `routes/web.php`, `app/Http/Middleware/EnsureUserHasRole.php` |
| Controladores (17) | `app/Http/Controllers` |
| Servicios de negocio | `app/Services` (tabla siguiente) |
| Modelos Eloquent | `app/Models` |
| Vistas | `resources/views` |
| Base de datos (12 tablas) | `database/migrations`; descripción en [`modelo-datos.md`](modelo-datos.md) |
| Comandos y tareas programadas | `routes/console.php` |

| Servicio | Responsabilidad |
|----------|-----------------|
| `InventarioService` | Todo lo que cambia stock: venta FEFO, recepción, merma y ajuste. Usa una transacción y bloqueo de filas (`lockForUpdate`) y deja su movimiento en la bitácora |
| `PuntoReordenService` | Punto de reorden según la demanda de los últimos 28 días |
| `OrdenCompraService` | Cantidad sugerida y órdenes de compra por proveedor |
| `ClasificacionAbcService` | Clase ABC por valor vendido en 90 días (A 80 %, B 95 %, C el resto) |
| `IndicadorService` | Indicadores M1.1, M1.2, M2 y M3 del tablero, calculados al momento |
| `CatalogoCsvService` | Lectura, validación e importación del catálogo desde CSV |

Las decisiones de diseño y los diagramas de capas y despliegue están en el [README](../README.md#arquitectura).

## 2. Requisitos

| Componente | Versión | Comprobación |
|------------|---------|--------------|
| PHP | 8.4, con `pdo_mysql` o `pdo_sqlite` | `php -v` |
| Composer | 2 | `composer --version` |
| Base de datos | MySQL 8.4 (producción) o SQLite (desarrollo) | `mysql --version` |
| Docker (opcional) | Docker Desktop | `docker --version` |

La CI rechaza cualquier versión distinta de PHP 8.4 y PHPUnit 13 (cambio CC-04).

## 3. Instalación local

### Con Docker, sin instalar PHP

```bash
git clone https://github.com/claudio4ndres/inventario-el-portal.git
cd inventario-el-portal
docker compose up --build        # http://localhost:8000
```

Con `DEMO_RESET=true`, que es el valor por defecto, la base se recrea con datos de demostración en cada inicio. Para cargar el escenario simulado de 28 días, descomenta `DEMO_SEEDER: "EscenarioPruebaSeeder"` en `docker-compose.yml`.

### Con PHP local

```bash
git config core.hooksPath .githooks   # rechaza mensajes de commit con firmas de IA
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve                     # http://localhost:8000
```

Los usuarios de demostración son `admin@elportal.test`, `caja@elportal.test` y `bodega@elportal.test`. Sus contraseñas están en `database/seeders/DatabaseSeeder.php` y sirven **solo** para desarrollo y pruebas.

## 4. Configuración

Las variables principales son estas. La lista completa está en `.env.example`.

| Variable | Producción | Uso |
|----------|------------|-----|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` | Nunca dejar `APP_DEBUG=true` en producción, porque expone detalles internos |
| `APP_KEY` | fija, generada una vez | Si cambia, se invalidan las sesiones y los datos cifrados |
| `APP_TIMEZONE` | `America/Santiago` | Fechas de ventas, vencimientos e indicadores |
| `DB_CONNECTION` y `DB_*` | `mysql` y `${{MySQL.*}}` | Conexión a MySQL por la red privada de Railway |
| `DEMO_RESET` | `false` | Con `true`, **borra la base** en cada inicio. Solo para demostraciones y pruebas |
| `DEMO_SEEDER` | sin definir | `EscenarioPruebaSeeder` carga 28 días simulados (solo en `web-pruebas`) |
| `SEED_DEMO` | `true` | Carga datos de demostración solo si la base está vacía |
| `RUN_SCHEDULER` | `true` | Levanta el planificador de tareas (punto de reorden diario) |
| `INVENTARIO_DIAS_POR_VENCER` | `7` | Ventana de días de la alerta de lotes por vencer (HU12) |
| `PHP_CLI_SERVER_WORKERS` | `4` | Peticiones simultáneas del servidor PHP |
| `SESSION_SECURE_COOKIE` | `true` | La cookie de sesión viaja solo por HTTPS |
| `LOG_CHANNEL` | `stderr` | Los registros aparecen en Railway |

**Qué hace el arranque del contenedor** (`docker/entrypoint.sh`):

1. Genera `APP_KEY` solo si no viene definida.
2. Si `DEMO_RESET=true`, ejecuta `migrate:fresh --seed`. Si no, ejecuta solo `migrate`, que aplica las migraciones nuevas sin borrar datos.
3. En producción, guarda en caché la configuración y las vistas.
4. Levanta el planificador si `RUN_SCHEDULER=true`.
5. Inicia `php artisan serve` en `PORT`.

## 5. Despliegue en Railway

El proyecto `inventario-el-portal` tiene cuatro servicios. La configuración de cada uno está en sus ajustes de Railway y no en el repositorio (cambio CC-06). La tabla completa está en [`gestion-configuracion.md`](gestion-configuracion.md#configuración-de-railway).

| Servicio | Origen | Función |
|----------|--------|---------|
| `web` | rama `main` | Producción, con dominio HTTPS |
| `web-pruebas` | rama `develop` | Ambiente de pruebas con el escenario simulado (SQLite propio) |
| `MySQL` | imagen `mysql:8` | Base de producción, con el volumen `mysql-volume` |
| `respaldo-mysql` | rama `main`, raíz `/infra/respaldo` | Respaldo diario (sección 7) |

**Publicar un cambio:**
1. Rama `feature/*` desde `develop`.
2. PR a `develop` con la CI en verde.
3. PR de `develop` a `main`.

Railway construye el `Dockerfile` y solo cambia el tráfico a la nueva versión cuando `/up` responde 200.

**Instalar desde cero:**
1. Crear el proyecto y un servicio MySQL.
2. Crear el servicio web desde el repositorio, rama `main`.
3. En *Settings*, configurar: *Dockerfile Path* `Dockerfile`, *Healthcheck Path* `/up` con 300 s, y *Restart Policy* `On Failure` con 5 reintentos.
4. Cargar las variables de la sección 4.
5. Generar el dominio público en el puerto 8000.
6. Crear `respaldo-mysql` según [`respaldo.md`](respaldo.md).

## 6. Tareas programadas y comandos

| Comando | Cuándo | Qué hace |
|---------|--------|----------|
| `inventario:recalcular-reorden` | Todos los días a las 05:00 (planificador) | Recalcula el punto de reorden con la demanda de 28 días (HU10) |
| `inventario:recalcular-abc` | A mano, o con el botón «Recalcular ABC» | Recalcula la clase ABC por valor vendido en 90 días (HU17) |
| `inventario:sembrar-demo` | En el arranque, si `SEED_DEMO=true` | Carga los datos de demostración solo si no hay usuarios |

Fuera de Docker, el planificador se activa con el cron del sistema: `* * * * * php artisan schedule:run`.

## 7. Respaldo y recuperación

El servicio `respaldo-mysql` vuelca la base de producción con `mysqldump --single-transaction` todos los días a las 07:00 UTC y conserva los últimos 7 respaldos. Tiene cuatro modos: `respaldo`, `simulacro`, `restaurar` y `listar`. El procedimiento completo, incluida la forma de deshacer una restauración, está en [`respaldo.md`](respaldo.md).

En la auditoría de cada hito se revisa que la última ejecución en la pestaña *Cron Runs* haya terminado bien.

## 8. Pruebas e integración continua

```bash
php artisan test                    # todas las pruebas (SQLite en memoria)
php artisan test --filter=VentaTest # una clase
```

GitHub Actions (`.github/workflows/tests.yml`) corre la batería completa en cada push y en cada PR, dos veces: con SQLite en memoria y con MySQL 8.4, el mismo motor de producción. La última ejecución en `main` (#104, 24-11-2026) pasó 167 pruebas con 1022 aserciones. `docker.yml` construye la imagen, comprueba que responda y la publica en `ghcr.io`.

**Regla:** no se integra nada con la CI en rojo.

La relación entre cada historia, su código y sus pruebas está en [`trazabilidad.md`](trazabilidad.md).

## 9. Gestión de la configuración

- **Ramas:** `feature/*` → `develop` → `main`. Solo `main` se despliega en producción.
- **Versiones:** cada línea base es un tag (`v0.1.0`, `v0.2.0`, `v1.0.0`, `v1.0.1`). Las notas de cada versión están en [`release-notes.md`](release-notes.md).
- **Cambios:** todo cambio de alcance, hito o plataforma se registra como CC-NN en [`gestion-configuracion.md`](gestion-configuracion.md).
- **Mensajes de commit:** Conventional Commits, con referencia a la HU y a la issue. El hook `.githooks/commit-msg` rechaza mensajes que mencionen herramientas de IA.

## 10. Mantenimiento

| Tarea | Frecuencia | Cómo |
|-------|------------|------|
| Revisar el respaldo diario | Semanal | Railway → `respaldo-mysql` → *Cron Runs* |
| Simulacro de restauración | Antes de cada cambio grande y al cerrar un hito | `RESPALDO_MODO=simulacro` (ver [`respaldo.md`](respaldo.md)) |
| Actualizar dependencias | Mensual | `composer update` en una rama, CI en verde y PR |
| Versión de PHP y avisos de la plataforma | Trimestral | Revisar el soporte de PHP 8.4 y los avisos de Railway (antecedentes: CC-04 y CC-06) |
| Cambiar las contraseñas de demostración | Antes del uso real | Pantalla Usuarios, como administrador |
| Marcar la lista de alta rotación | Antes del uso real | Productos → «Alta rotación» (necesaria para M2) |

## 11. Problemas frecuentes

| Síntoma | Causa probable | Solución |
|---------|----------------|----------|
| El despliegue queda en *Deploying* y falla | `/up` no responde: error en migraciones o en variables | Revisar los registros del despliegue en Railway; corregir y volver a desplegar. El anterior sigue activo |
| Todos los usuarios pierden la sesión después de un despliegue | Cambió `APP_KEY` | Restaurar el valor anterior de `APP_KEY` en las variables del servicio |
| Los formularios fallan detrás de HTTPS | Proxy sin confianza o `APP_URL` incorrecta | Comprobar `APP_URL` con `https://`; ver `DespliegueTest` |
| El punto de reorden no cambia | El planificador no corre | Confirmar `RUN_SCHEDULER=true` y revisar `storage/logs/scheduler.log` |
| La importación CSV rechaza filas | Errores de validación | La vista previa muestra el motivo de cada fila; ejemplos en [`catalogo/README.md`](catalogo/README.md) |
| `respaldo-mysql` termina con error | MySQL caído o volcado incompleto | Ver *Cron Runs* → *View logs*; el script descarta los volcados incompletos |
| Se borraron datos de producción | `DEMO_RESET=true` en `web` | Restaurar el último respaldo ([`respaldo.md`](respaldo.md)) y dejar `DEMO_RESET=false` |
