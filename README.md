# Inventario El Portal

Sistema web de gestión de inventario y ventas para el **almacén El Portal** (Valle Grande, Lampa).
Proyecto de título de Ingeniería en Computación e Informática — Claudio Figueroa Arias, 2026.

**Estado:** MPV (Mínimo Producto Viable): versión `v0.1.0-mpv`, que corresponde al hito H2 del plan de proyecto.

## Alcance del MPV

| HU | Funcionalidad | Control |
|----|---------------|---------|
| HU01 | Inicio de sesión por rol (administrador, vendedor, bodeguero) | K8 |
| HU02 | Mantenedor de productos (código de barras, precio con IVA, punto de reorden, clase ABC) | K5 |
| HU03 | Recepción de mercadería con lote y fecha de vencimiento | K2 |
| HU04 | Venta con descuento automático de stock por lote (primero el que vence antes) | K1 |
| HU05 | Búsqueda por código de barras (lector USB) | K5 |
| HU06 | Alertas de productos en o bajo su punto de reorden | K3 |
| HU07 | Bitácora de movimientos (entradas y salidas con usuario y stock resultante) | K8 |

## Requisitos

- PHP 8.3 o superior, con las extensiones `pdo_sqlite` o `pdo_mysql`
- Composer 2
- MySQL 8.4 (producción) o SQLite (desarrollo rápido)

## Instalación local

```bash
git clone https://github.com/claudio4ndres/inventario-el-portal.git
cd inventario-el-portal
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
- **Tags:** marcan las líneas base por hito: `v0.1.0-mpv` (H2), `v0.2.0-diseno` (H3), `v1.0.0-construccion` (H4).
- El detalle está en [`docs/gestion-configuracion.md`](docs/gestion-configuracion.md).

## Estructura

```
app/Services/InventarioService.php   reglas de negocio: recepción y venta con descuento por lote (transaccional)
app/Http/Middleware/EnsureUserHasRole.php   control de acceso por rol
database/migrations/                  modelo de datos
tests/Feature/                        pruebas por historia de usuario
docs/                                 modelo de datos y gestión de configuración
```
