# Inventario El Portal

Sistema web de gestión de inventario y ventas para el **almacén El Portal** (Valle Grande, Lampa).
Proyecto de título de Ingeniería en Computación e Informática — Claudio Figueroa Arias, 2026.

**Estado:** `main` contiene el prototipo aprobado en el hito H2 (HU01–HU07, línea base LB-2). `develop` ya integra HU01–HU15; las HU16 y HU17 completan el MPV del hito H4. El avance se registra en `docs/backlog.md` y en los [hitos de GitHub](https://github.com/claudio4ndres/inventario-el-portal/milestones).

## Alcance construido

| HU | Funcionalidad | Control |
|----|---------------|---------|
| HU01 | Inicio de sesión por rol (administrador, vendedor, bodeguero) | K8 |
| HU02 | Mantenedor de productos (código de barras, precio con IVA, punto de reorden, clase ABC) | K5 |
| HU03 | Recepción de mercadería con lote y fecha de vencimiento | K2 |
| HU04 | Venta con descuento automático de stock por lote (primero el que vence antes) | K1 |
| HU05 | Búsqueda por código de barras (lector USB) | K5 |
| HU06 | Alertas de productos en o bajo su punto de reorden | K3 |
| HU07 | Bitácora de movimientos (entradas y salidas con usuario y stock resultante) | K8 |
| HU08 | Gestión de usuarios y roles, con activación y desactivación | K8 |
| HU09 | Gestión de proveedores con RUT validado (módulo 11) | K2 |
| HU10 | Punto de reorden calculado con la demanda de 28 días | K3 |
| HU11 | Orden de compra sugerida y recepción contra OC | K3, K2 |
| HU12 | Alertas de lotes por vencer (FEFO) | K4 |
| HU13 | Registro de mermas con cantidad, costo y motivo | K7 |
| HU14 | Conteo cíclico por clase ABC, ajustes y exactitud del inventario | K6 |
| HU15 | Tablero de indicadores M1.1, M1.2, M2 y M3 con gráfico de quiebres por semana | K7 |

HU01–HU07 forman el prototipo (H2, en `main`); HU08–HU15 están en `develop` y se liberan a `main` al aprobar el hito correspondiente.

## Requisitos

- PHP 8.3 o superior, con las extensiones `pdo_sqlite` o `pdo_mysql`
- Composer 2
- MySQL 8.4 (producción) o SQLite (desarrollo rápido)

## Ejecutar con Docker (sin instalar PHP)

Requisito: [Docker Desktop](https://www.docker.com/products/docker-desktop/).

```bash
git clone https://github.com/claudio4ndres/inventario-el-portal.git
cd inventario-el-portal
git checkout develop
docker compose up --build        # primera vez: unos minutos
```

Abre http://localhost:8000. La base de datos se recrea con los datos de demostración en cada inicio (`DEMO_RESET=true`).

**Imagen ya construida:** GitHub Actions publica cada versión de `develop` en GitHub Container Registry:

```bash
docker login ghcr.io -u claudio4ndres      # contraseña: token con permiso read:packages
docker run --rm -p 8000:8000 ghcr.io/claudio4ndres/inventario-el-portal:develop
```

## Instalación local

```bash
git clone https://github.com/claudio4ndres/inventario-el-portal.git
cd inventario-el-portal
git config core.hooksPath .githooks   # activa el control de mensajes de commit
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite        # en Windows: type nul > database\database.sqlite
php artisan migrate --seed
php artisan serve                     # http://localhost:8000
```

Para usar **MySQL** (por ejemplo con XAMPP o Laragon), cambia el archivo `.env`:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=inventario_el_portal
DB_USERNAME=root
DB_PASSWORD=
```

### Usuarios de demostración

Los datos son ficticios. La contraseña de los tres usuarios es `ElPortal2026`.

| Rol | Correo |
|-----|--------|
| Administrador | admin@elportal.test |
| Vendedor (caja) | caja@elportal.test |
| Bodeguero (apoyo) | bodega@elportal.test |

## Pruebas

```bash
php artisan test
```

GitHub Actions ejecuta las pruebas en cada push, sobre SQLite y sobre MySQL 8.4 (`.github/workflows/tests.yml`).

## Gestión de la configuración

- **Ramas:** `main` contiene las líneas base aprobadas por hito; `develop` es la rama de integración; `feature/hu-xx-*` se usa para cada historia de usuario.
- **Commits:** siguen [Conventional Commits](https://www.conventionalcommits.org/es/) (`feat:`, `fix:`, `test:`, `docs:`, `chore:`) y referencian la HU.
- **Tags:** marcan las líneas base por hito: `v0.1.0` (H2, prototipo), `v0.2.0` (H3), `v1.0.0` (H4, MPV) y `v1.1.0` (H5, piloto).
- **Hitos e issues:** cada HU tiene su issue en GitHub (HU01 → #1 … HU17 → #17), asociada a su hito. Los commits la referencian (`Refs: HU-14, #14`) y se cierra al integrarse en `develop`.
- El detalle está en [`docs/gestion-configuracion.md`](docs/gestion-configuracion.md).

## Estructura

```
app/Services/InventarioService.php   reglas de negocio: recepción y venta con descuento por lote (transaccional)
app/Http/Middleware/EnsureUserHasRole.php   control de acceso por rol
database/migrations/                  modelo de datos
tests/Feature/                        pruebas por historia de usuario
docs/                                 modelo de datos y gestión de configuración
```
