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
| LB-2 Prototipo | H2 (23-10-2026) | Prototipo de reducción de riesgo: HU01–HU07 con pruebas en verde | tag `v0.1.0` |
| LB-3 Diseño | H3 (30-10-2026) | Modelo de datos, pantallas (mockups y capturas) y controles aprobados | tag `v0.2.0` |
| LB-4 MPV | H4 (10-12-2026) | RF1–RF9 (HU01–HU17) con pruebas unitarias y de integración aprobadas | tag `v1.0.0` |
| LB-5 Piloto | H5 (18-12-2026) | Versión operando en el local con datos migrados; métricas M1–M5 medidas | tag `v1.1.0` |

El hito **H6 · Presentación final (22-12-2026)** no congela código: entrega el informe y la presentación (IC-09).

**Hitos en GitHub:** cada hito del plan es un *milestone* del repositorio (H0 a H6, con su fecha) y agrupa las issues de sus HU, entregables de gestión y cambios. Un hito se da por cumplido cuando sus issues están cerradas con evidencia y existe el tag de su línea base.

## 3. Control de versiones (Git)

```
main      ●──────────────●─────────────────●    ← solo líneas base (tags por hito)
           \            /                 /
develop     ●──●──●──●──●──●──●──●──●──●──●      ← integración
               \  /  \  /     \    /
feature/hu-xx   ●     ●        ●──●             ← una rama por historia de usuario
```

- **Commits** con Conventional Commits (`feat(ventas): …`, `fix: …`, `test: …`) y la referencia a la HU y a su issue (`Refs: HU-04, #4`).
- **Mensajes sin firmas de IA:** el hook `.githooks/commit-msg` rechaza todo commit cuyo mensaje mencione a Claude o Anthropic. Se activa una vez por clon con `git config core.hooksPath .githooks`.
- **Integración:** una rama `feature/*` se integra a `develop` solo con las pruebas de GitHub Actions en verde (SQLite y MySQL 8.4). Al integrarla se cierra la issue de la HU con el commit de integración y el número de pruebas como evidencia.
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
| CC-02 | 26-10-2026 | Avance real del desarrollo (S19–S20) | Se construyen las HU08–HU14 antes de aprobar el diseño (H3), en solapamiento controlado con la fase de diseño; se agrega el riesgo R10 (dominio insuficiente del código generado con IA) | El MPV puede quedar listo antes de H4 y libera tiempo para el piloto (H5); el diseño de H3 debe validar los módulos ya construidos | Aprobado en el acta de H3 (issue #41; firma pendiente) |
| CC-03 | 28-10-2026 | Actualización de la plataforma | PHP 8.3 → 8.4 y PHPUnit 12 → 13 (`composer.json`, CI y Dockerfile); la CI verifica ambas versiones antes de ejecutar las pruebas | Sin cambios funcionales; se valida con la batería completa de pruebas en SQLite y MySQL 8.4; los entornos locales y del piloto deben usar PHP 8.4 | En revisión |

## 5. Auditoría de la configuración

Al cerrar cada hito se verifica con una lista de chequeo:

- El tag existe y apunta al commit aprobado.
- La CI está en verde.
- La matriz de trazabilidad HU → RF → causa → control está completa.
- Los documentos tienen la versión correcta en Drive.
