# Plan de gestión de la configuración

La gestión de la configuración asegura que cada versión del producto y de su documentación esté **identificada, controlada y sea reproducible**. Se aplica según el ciclo de vida elegido: cascada con prototipo de reducción de riesgo y construcción por módulos.

## 1. Ítems de configuración (IC)

| ID | Ítem de configuración | Herramienta | Ubicación | Responsable |
|----|-----------------------|-------------|-----------|-------------|
| IC-01 | Código fuente (Laravel) | Git / GitHub | `app/`, `routes/`, `resources/` | Autor |
| IC-02 | Modelo de datos (migraciones) | Git / GitHub | `database/migrations/` | Autor |
| IC-03 | Pruebas automatizadas | Git / GitHub + GitHub Actions | `tests/`, `.github/workflows/` | Autor |
| IC-04 | Dependencias (`composer.json` / `composer.lock`) | Git / Composer | raíz del repositorio | Autor |
| IC-05 | Especificación de requerimientos (HU, RF, RNF) | Google Drive (con historial) | `/Proyecto/Requerimientos` | Autor |
| IC-06 | Diagramas (contexto, casos de uso, ER) y mockups | Google Drive + `docs/` | `/Proyecto/Diseño` | Autor |
| IC-07 | Plan de proyecto, carta Gantt y registro de riesgos | Google Drive | `/Proyecto/Gestión` | Autor |
| IC-08 | Actas de hito y de validación con el administrador | Google Drive (PDF firmado) | `/Proyecto/Actas` | Autor y administrador |
| IC-09 | Informes y presentaciones | Google Drive | `/Proyecto/Entregas` | Autor |
| IC-10 | Configuración de entornos (`.env.example`) | Git | raíz del repositorio | Autor |

**Nomenclatura de documentos:** `<curso>_<documento>_v<mayor>.<menor>_<AAAA-MM-DD>`. Por ejemplo, `AINC422_requerimientos_v2.0_2026-10-19`.

## 2. Líneas base (una por hito)

| Línea base | Hito | Contenido congelado | Identificador |
|------------|------|---------------------|---------------|
| LB-1 Requerimientos | H1 (16-10-2026) | Requerimientos v1.0 validados por el administrador | `AINC_requerimientos_v1.0` |
| LB-1b Requerimientos (cambio aprobado) | Observaciones de la profesora guía (19-10-2026) | Requerimientos v2.0: RF1–RF9, C1–C12, K1–K8, OE1–OE5 | `AINC_requerimientos_v2.0` |
| LB-2 Prototipo / MPV | H2 (23-10-2026) | MPV HU01–HU07 con pruebas en verde | tag `v0.1.0-mpv` |
| LB-3 Diseño | H3 (30-10-2026) | Modelo de datos completo, mockups y controles | tag `v0.2.0-diseno` |
| LB-4 Construcción | H4 (10-12-2026) | Todos los módulos con pruebas unitarias y de integración | tag `v1.0.0-construccion` |
| LB-5 Producción | H7 (16-04-2027) | Versión en producción en el local | tag `v1.1.0` |

## 3. Control de versiones (Git)

```
main      ●──────────────●─────────────────●    ← solo líneas base (tags por hito)
           \            /                 /
develop     ●──●──●──●──●──●──●──●──●──●──●      ← integración
               \  /  \  /     \    /
feature/hu-xx   ●     ●        ●──●             ← una rama por historia de usuario
```

- **Commits** con Conventional Commits (`feat(ventas): …`, `fix: …`, `test: …`) y la referencia a la HU (`Refs: HU04`).
- **Integración:** una rama `feature/*` se integra a `develop` solo con las pruebas de GitHub Actions en verde (SQLite y MySQL 8.4).
- **Liberación:** `develop` se integra a `main` al aprobar un hito, y se crea el tag de la línea base.

## 4. Control de cambios

1. **Solicitud:** quién la pide, qué cambia y por qué (por ejemplo, una observación de la profesora guía o del administrador).
2. **Análisis de impacto:** qué HU, RF, causas, controles, plan y cronograma afecta.
3. **Decisión:** el autor la aprueba con visto bueno de la profesora guía si afecta el alcance o un hito.
4. **Implementación:** se crea una nueva versión del ítem (v1.x → v2.0) y se registra en el historial de cambios.
5. **Verificación:** se revisa la trazabilidad y las pruebas en verde.

### Registro de cambios aplicado

| N.º | Fecha | Origen | Cambio | Impacto | Estado |
|-----|-------|--------|--------|---------|--------|
| CC-01 | 19-10-2026 | Observaciones de la profesora guía al anteproyecto | Caso real (almacén El Portal); causas C1–C12 con C4 y C2 como principales; controles K1–K8; nuevo OE5; RF1–RF9; descuento de stock por lote; plan de 26 semanas en 2 etapas | HU repriorizadas; el lote pasa a formar parte del MPV; cronograma actualizado | Aprobado |

## 5. Auditoría de la configuración

Al cerrar cada hito se verifica con una lista de chequeo:

- El tag existe y apunta al commit aprobado.
- La CI está en verde.
- La matriz de trazabilidad HU → RF → causa → control está completa.
- Los documentos tienen la versión correcta en Drive.
