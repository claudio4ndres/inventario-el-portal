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
| IC-11 | Base de datos de producción (MySQL) | Servicio cron `respaldo-mysql` en Railway (`mysqldump` diario, últimos 7) | código en `infra/respaldo/`; procedimiento en [`docs/respaldo.md`](respaldo.md) | Autor |
| IC-12 | Configuración de los servicios de Railway | Ajustes de cada servicio en Railway (registrados abajo) | proyecto `inventario-el-portal`, ambiente `production` | Autor |

### Configuración de Railway

Desde el 01-12-2026 Railway no lee `railway.json` ni `railway.toml` (CC-06). La configuración de cada servicio está en sus ajustes y se registra aquí. Si se cambia en Railway, se actualiza esta tabla en el mismo cambio.

| Servicio | Origen | Construcción | Despliegue |
|----------|--------|--------------|------------|
| `web` | `main` | `Dockerfile` | Healthcheck `/up` (300 s); reinicio *On Failure*, 5 reintentos; dominio público, puerto 8000 |
| `web-pruebas` | `develop` | `Dockerfile` | Healthcheck `/up` (300 s); reinicio *On Failure*, 5 reintentos; dominio público, puerto 8000 |
| `respaldo-mysql` | `main`, raíz `/infra/respaldo` | `Dockerfile` | Cron `0 7 * * *`; reinicio *Never*; volumen `respaldos-volume` en `/respaldos` |
| `MySQL` | imagen `mysql:8` | — | Volumen `mysql-volume` en `/var/lib/mysql` |

Las variables de entorno de cada servicio están en el `README.md` (web) y en [`respaldo.md`](respaldo.md) (respaldo). Los secretos (`APP_KEY` y las contraseñas) quedan solo en Railway.

**Nomenclatura de documentos:** `<curso>_<documento>_v<mayor>.<menor>_<AAAA-MM-DD>`. Por ejemplo, `AINC422_requerimientos_v2.0_2026-10-19`.

## 2. Líneas base (una por hito)

| Línea base | Hito | Contenido congelado | Identificador |
|------------|------|---------------------|---------------|
| LB-1 Requerimientos | H1 (16-10-2026) | Requerimientos v1.0 validados por el administrador | `AINC_requerimientos_v1.0` |
| LB-1b Requerimientos (cambio aprobado) | Observaciones de la profesora de licenciatura, Jenny Pantoja Blyde (19-10-2026) | Requerimientos v2.0: RF1–RF9, C1–C12, K1–K8, OE1–OE5 | `AINC_requerimientos_v2.0` |
| LB-2 Prototipo | H2 (23-10-2026) | Prototipo de reducción de riesgo: HU01–HU07 con pruebas en verde | tag `v0.1.0` → commit `8b9c39e` de `main` |
| LB-3 Diseño | H3 (30-10-2026) | Modelo de datos, pantallas (mockups y capturas) y controles aprobados | tag `v0.2.0` → commit `bb6925b` de `develop` (estado aprobado en H3) |
| LB-4 MPV | H4 (10-12-2026; entregada el 31-10-2026 por CC-03) | RF1–RF9 (HU01–HU17) con 159 pruebas unitarias y de integración aprobadas | tag `v1.0.0` → commit de liberación en `main` del 31-10-2026 |
| LB-5 Validación (redefinida por CC-05) | H5 (18-12-2026) | Versión validada en el ambiente de pruebas: catálogo cargado por CSV, M1.1, M1.2, M2 y M3 verificados con el escenario simulado, manual de usuario y respaldo diario | tag `v1.1.0` |

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

1. **Solicitud:** quién la pide, qué cambia y por qué (por ejemplo, una observación del profesor guía o del administrador).
2. **Análisis de impacto:** qué HU, RF, causas, controles, plan y cronograma afecta.
3. **Decisión:** el autor la aprueba con visto bueno del profesor guía, Tomás Sepúlveda Caroca, si afecta el alcance o un hito.
4. **Implementación:** se crea una nueva versión del ítem (v1.x → v2.0) y se registra en el historial de cambios.
5. **Verificación:** se revisa la trazabilidad y las pruebas en verde.

### Registro de cambios aplicado

| N.º | Fecha | Origen | Cambio | Impacto | Estado |
|-----|-------|--------|--------|---------|--------|
| CC-01 | 19-10-2026 | Observaciones de la profesora de licenciatura, Jenny Pantoja Blyde, al anteproyecto (Etapa 1) | Caso real (almacén El Portal); causas C1–C12 con C4 y C2 como principales; controles K1–K8; nuevo OE5; RF1–RF9; descuento de stock por lote; plan de 26 semanas en 2 etapas | HU repriorizadas; el lote pasa a formar parte del MPV; cronograma actualizado | Aprobado |
| CC-02 | 26-10-2026 | Avance real del desarrollo (S19–S20) | Se construyen las HU08–HU14 antes de aprobar el diseño (H3), en solapamiento controlado con la fase de diseño; se agrega el riesgo R10 (dominio insuficiente del código generado con IA) | El MPV puede quedar listo antes de H4 y libera tiempo para el piloto (H5); el diseño de H3 debe validar los módulos ya construidos | Aprobado en el acta de H3 (issue #41; firma pendiente) |
| CC-03 | 31-10-2026 | Avance real: las 17 HU del MPV terminadas el 29-10-2026 (4 semanas antes de lo planificado) | Se adelanta la fase de pruebas y entrega del MPV: pruebas de integración (flujos de punta a punta y matriz de acceso) y liberación de la LB-4 (`v1.0.0`) el 31-10-2026 en lugar de S26 | H4 puede cerrarse antes del 10-12-2026; las semanas liberadas se destinan a la línea base de métricas (R8), a la revisión del código (R10) y a preparar el piloto (H5), cuya fecha no cambia | Aplicado; acta de H4 pendiente de firma (issue #27) |
| CC-04 | 11-11-2026 | Actualización de la plataforma por seguridad (riesgo R11) | PHP 8.3 → 8.4 y PHPUnit 12 → 13 (`composer.json`, CI y Dockerfile); la CI verifica ambas versiones antes de ejecutar las pruebas | Sin cambios funcionales; se valida con la batería completa de pruebas en SQLite y MySQL 8.4; los entornos locales y del piloto deben usar PHP 8.4 | Aplicado (PR #43, 16-11-2026) |
| CC-05 | 23-11-2026 | Riesgo R12 materializado: no se logró coordinar una reunión con todos los actores del almacén (dueño-administrador, caja y bodega) y el autor, por lo que no se pudo acordar la puesta en marcha del piloto ni capacitar a los usuarios (issue #47) | H5 pasa de piloto en el local a validación en ambiente de pruebas: catálogo por importación CSV, escenario simulado de 28 días (servicio `web-pruebas`), prueba de aceptación guiada y manual de usuario; el piloto real queda como recomendación posterior (issues #48 y #49; #28 cerrada como no planificada) | OE5 parcialmente logrado; M2 y M3 se informan como verificación con datos simulados; fechas de H5 y H6 sin cambio | Aprobado: visto bueno verbal del profesor guía, Tomás Sepúlveda Caroca, registrado el 24-11-2026, con la condición de explicar la causa en el informe |
| CC-06 | 24-11-2026 | Aviso de la plataforma: Railway deja de leer `railway.json` y `railway.toml` el 01-12-2026 (Config as Code) | La configuración de despliegue (Dockerfile, healthcheck `/up`, política de reinicio y cron del respaldo) pasa a los ajustes de cada servicio y se registra en la sección «Configuración de Railway»; se eliminan `railway.json` e `infra/respaldo/railway.toml` | Sin cambios funcionales; se verifica con un despliegue de cada servicio sin los archivos | Aplicado |

## 5. Auditoría de la configuración

Al cerrar cada hito se verifica con una lista de chequeo:

- El tag existe y apunta al commit aprobado.
- La CI está en verde.
- La matriz de trazabilidad HU → RF → causa → control está completa.
- Los documentos tienen la versión correcta en Drive.
- La última ejecución del cron `respaldo-mysql` terminó bien (IC-11, pestaña *Cron Runs*).
