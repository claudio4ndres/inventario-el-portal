# Notas de versión — Inventario El Portal

Cada versión corresponde a una línea base (LB) definida en `docs/gestion-configuracion.md`.
Solo `main` se publica en producción (Railway); `develop` integra los cambios probados.

| Versión | Fecha | Commit (`main`) | Línea base | Pruebas en la CI |
|---------|-------|-----------------|------------|------------------|
| v1.0.1 (propuesta) | 16-11-2026 | `66d6d3e` | Mantención de LB-4 | 164 (989 aserciones) |
| v1.0.0 | 31-10-2026 | `c6745b7` | LB-4 · MPV (H4) | 159 |
| v0.2.0 | 29-10-2026 | `bb6925b` (`develop`) | LB-3 · Diseño (H3) | 150 |
| v0.1.0 | 19-10-2026 | `8b9c39e` | LB-2 · Prototipo (H2) | 33 |

## v1.0.1 — 16-11-2026 (en producción, tag pendiente)

**Cambios**
- Plataforma actualizada a PHP 8.4 y PHPUnit 13 por seguridad (CC-04, RNF4, riesgo R11). La CI falla si las versiones no corresponden (PR #43).
- Indicador M2: cuenta los quiebres por día y producto sobre una lista fija de alta rotación, igual que la línea base manual. Nueva columna `productos.alta_rotacion` (migración `2026_11_11_000001`) e interruptor «Alta rotación» en el mantenedor de productos (PR #44).

**Acción antes del piloto:** en producción todos los productos quedan con `alta_rotacion = false`; el administrador debe marcar la lista acordada.

**Verificación:** 164 pruebas en verde en SQLite y MySQL 8.4 (ejecución #72); despliegue en Railway exitoso.

## v1.0.0 — 31-10-2026 · LB-4 MPV

- HU01–HU17 (RF1–RF9) integradas; pruebas de integración de punta a punta y matriz de acceso por rol (159 pruebas).
- Publicación en Railway con MySQL persistente y HTTPS (`43f87d3`, `1da3b74`).
- 01-11-2026: menú de navegación en submenús y menú del administrador (`2d9f49b`, PR #42).

## v0.2.0 — 29-10-2026 · LB-3 Diseño

- Modelo de datos de 12 tablas, pantallas y controles K1–K8 aprobados en H3; HU08–HU17 construidas en paralelo al diseño (CC-02).

## v0.1.0 — 19-10-2026 · LB-2 Prototipo

- Prototipo de reducción de riesgo: HU01–HU07 (sesión por rol, productos, recepción por lote, venta FEFO, búsqueda por código de barras, alertas y bitácora).

## Integrado en `develop`, aún no publicado

- Escenario de prueba simulado de 28 días para validar el tablero (PR #46). Se usa solo en el ambiente de pruebas; no cambia producción.
