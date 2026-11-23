# Matriz de trazabilidad

Relaciona cada historia de usuario (HU) con el requerimiento que cumple, la causa del problema que ataca, el control que implementa y el objetivo específico al que aporta, y la ubica en el código, en las pruebas automatizadas y en el historial de Git.

- **Causas (C1–C12)** y **controles (K1–K8)**: diagnóstico y diseño del informe del proyecto (tablas 3 y 14).
- **Código**: enlaces al commit `66d6d3e`, la versión en producción (v1.0.1).
- **Pruebas**: archivo en `tests/` y cantidad de casos; todas corren en la CI en SQLite y MySQL 8.4.
- **Merge**: commit con que la rama de la HU se integró en `develop`.

## Historia de usuario → código → prueba

| HU | Historia | RF | Causa | Control | Objetivo | Código principal | Pruebas | Merge | Issue |
|----|----------|----|-------|---------|----------|------------------|---------|-------|-------|
| HU01 | Iniciar sesión por rol | RF9 | C8 | K8 | OE5 | [`AuthController.php`](https://github.com/claudio4ndres/inventario-el-portal/blob/66d6d3e/app/Http/Controllers/AuthController.php), [`EnsureUserHasRole::handle`](https://github.com/claudio4ndres/inventario-el-portal/blob/66d6d3e/app/Http/Middleware/EnsureUserHasRole.php#L15) | AutenticacionTest (8) | [`9a01303`](https://github.com/claudio4ndres/inventario-el-portal/commit/9a01303) | [#1](https://github.com/claudio4ndres/inventario-el-portal/issues/1) |
| HU02 | Gestionar productos | RF1 | C2, C11 | K5 | OE2 | [`ProductoController.php`](https://github.com/claudio4ndres/inventario-el-portal/blob/66d6d3e/app/Http/Controllers/ProductoController.php) | ProductoTest (5) | [`121ceaf`](https://github.com/claudio4ndres/inventario-el-portal/commit/121ceaf) | [#2](https://github.com/claudio4ndres/inventario-el-portal/issues/2) |
| HU03 | Recepción con lote y vencimiento | RF3 | C1, C10 | K2 | OE1, OE3 | [`InventarioService::registrarRecepcion`](https://github.com/claudio4ndres/inventario-el-portal/blob/66d6d3e/app/Services/InventarioService.php#L41) | RecepcionTest (4) | [`d2b6747`](https://github.com/claudio4ndres/inventario-el-portal/commit/d2b6747) | [#3](https://github.com/claudio4ndres/inventario-el-portal/issues/3) |
| HU04 | Venta con descuento FEFO | RF2 | C4, C1, C5 | K1 | OE1 | [`VentaController.php`](https://github.com/claudio4ndres/inventario-el-portal/blob/66d6d3e/app/Http/Controllers/VentaController.php), [`InventarioService::registrarVenta`](https://github.com/claudio4ndres/inventario-el-portal/blob/66d6d3e/app/Services/InventarioService.php#L103) | VentaTest (8) | [`74145c3`](https://github.com/claudio4ndres/inventario-el-portal/commit/74145c3) | [#4](https://github.com/claudio4ndres/inventario-el-portal/issues/4) |
| HU05 | Búsqueda por código de barras | RF2 (RNF1) | C7 | K5 | OE1 | [`ProductoController.php`](https://github.com/claudio4ndres/inventario-el-portal/blob/66d6d3e/app/Http/Controllers/ProductoController.php) | ProductoTest (1) | [`121ceaf`](https://github.com/claudio4ndres/inventario-el-portal/commit/121ceaf) | [#5](https://github.com/claudio4ndres/inventario-el-portal/issues/5) |
| HU06 | Alertas de punto de reorden | RF4 | C2 | K3 | OE2 | [`AlertaController.php`](https://github.com/claudio4ndres/inventario-el-portal/blob/66d6d3e/app/Http/Controllers/AlertaController.php) | AlertaYBitacoraTest (3) | [`a6d440c`](https://github.com/claudio4ndres/inventario-el-portal/commit/a6d440c) | [#6](https://github.com/claudio4ndres/inventario-el-portal/issues/6) |
| HU07 | Bitácora de movimientos | RNF2 | C6 | K8 | OE4 | [`MovimientoController.php`](https://github.com/claudio4ndres/inventario-el-portal/blob/66d6d3e/app/Http/Controllers/MovimientoController.php) | AlertaYBitacoraTest (2) | [`a6d440c`](https://github.com/claudio4ndres/inventario-el-portal/commit/a6d440c) | [#7](https://github.com/claudio4ndres/inventario-el-portal/issues/7) |
| HU08 | Gestionar usuarios y roles | RF9 | C8 | K8 | OE5 | [`UsuarioController.php`](https://github.com/claudio4ndres/inventario-el-portal/blob/66d6d3e/app/Http/Controllers/UsuarioController.php) | UsuarioTest (16) | [`00d4349`](https://github.com/claudio4ndres/inventario-el-portal/commit/00d4349) | [#8](https://github.com/claudio4ndres/inventario-el-portal/issues/8) |
| HU09 | Proveedores con RUT validado | RF3 | C1 | K2 | OE1 | [`ProveedorController.php`](https://github.com/claudio4ndres/inventario-el-portal/blob/66d6d3e/app/Http/Controllers/ProveedorController.php), [`Rut::calcularDv`](https://github.com/claudio4ndres/inventario-el-portal/blob/66d6d3e/app/Support/Rut.php#L20) | ProveedorTest (17), RutTest (4) | [`d3c1195`](https://github.com/claudio4ndres/inventario-el-portal/commit/d3c1195) | [#9](https://github.com/claudio4ndres/inventario-el-portal/issues/9) |
| HU12 | Lotes por vencer (FEFO) | RF5 | C10 | K4 | OE3 | [`Lote::scopePorVencer`](https://github.com/claudio4ndres/inventario-el-portal/blob/66d6d3e/app/Models/Lote.php#L43), [`LoteController.php`](https://github.com/claudio4ndres/inventario-el-portal/blob/66d6d3e/app/Http/Controllers/LoteController.php) | LotePorVencerTest (9) | [`59f9e40`](https://github.com/claudio4ndres/inventario-el-portal/commit/59f9e40) | [#12](https://github.com/claudio4ndres/inventario-el-portal/issues/12) |
| HU13 | Mermas con costo y motivo | RF6 | C12 | K7 | OE3 | [`InventarioService::registrarMerma`](https://github.com/claudio4ndres/inventario-el-portal/blob/66d6d3e/app/Services/InventarioService.php#L185), [`MermaController.php`](https://github.com/claudio4ndres/inventario-el-portal/blob/66d6d3e/app/Http/Controllers/MermaController.php) | MermaTest (11) | [`5517754`](https://github.com/claudio4ndres/inventario-el-portal/commit/5517754) | [#13](https://github.com/claudio4ndres/inventario-el-portal/issues/13) |
| HU10 | Punto de reorden calculado | RF4 | C2 | K3 | OE2 | [`PuntoReordenService::calcular`](https://github.com/claudio4ndres/inventario-el-portal/blob/66d6d3e/app/Services/PuntoReordenService.php#L60) | PuntoReordenTest (10) | [`ecf70a9`](https://github.com/claudio4ndres/inventario-el-portal/commit/ecf70a9) | [#10](https://github.com/claudio4ndres/inventario-el-portal/issues/10) |
| HU11 | Orden de compra sugerida | RF4, RF3 | C2, C1 | K3, K2 | OE2 | [`OrdenCompraService::cantidadSugerida`](https://github.com/claudio4ndres/inventario-el-portal/blob/66d6d3e/app/Services/OrdenCompraService.php#L26), [`OrdenCompraController.php`](https://github.com/claudio4ndres/inventario-el-portal/blob/66d6d3e/app/Http/Controllers/OrdenCompraController.php) | OrdenCompraTest (12) | [`dcfee94`](https://github.com/claudio4ndres/inventario-el-portal/commit/dcfee94) | [#11](https://github.com/claudio4ndres/inventario-el-portal/issues/11) |
| HU14 | Conteo cíclico y ajustes | RF7 | C3 | K6 | OE1, OE4 | [`InventarioService::registrarConteo`](https://github.com/claudio4ndres/inventario-el-portal/blob/66d6d3e/app/Services/InventarioService.php#L237), [`Conteo::programacion`](https://github.com/claudio4ndres/inventario-el-portal/blob/66d6d3e/app/Models/Conteo.php#L77) | ConteoTest (9) | [`da1277b`](https://github.com/claudio4ndres/inventario-el-portal/commit/da1277b) | [#14](https://github.com/claudio4ndres/inventario-el-portal/issues/14) |
| HU15 | Tablero de indicadores | RF8 | C6 | K7 | OE4 | [`IndicadorService::calcular`](https://github.com/claudio4ndres/inventario-el-portal/blob/66d6d3e/app/Services/IndicadorService.php#L33) | IndicadorTest (9) | [`2b8567d`](https://github.com/claudio4ndres/inventario-el-portal/commit/2b8567d) | [#15](https://github.com/claudio4ndres/inventario-el-portal/issues/15) |
| HU16 | Importar catálogo CSV | RF1 | C1 | K5 | OE5 | [`CatalogoCsvService::analizar`](https://github.com/claudio4ndres/inventario-el-portal/blob/66d6d3e/app/Services/CatalogoCsvService.php#L37) | ImportacionCatalogoTest (10) | [`f9e5eae`](https://github.com/claudio4ndres/inventario-el-portal/commit/f9e5eae) | [#16](https://github.com/claudio4ndres/inventario-el-portal/issues/16) |
| HU17 | Clasificación ABC | RF8 | C11 | K6, K7 | OE2, OE4 | [`ClasificacionAbcService::clasificar`](https://github.com/claudio4ndres/inventario-el-portal/blob/66d6d3e/app/Services/ClasificacionAbcService.php#L75) | ClasificacionAbcTest (9 + 3 unitarias) | [`62e6804`](https://github.com/claudio4ndres/inventario-el-portal/commit/62e6804) | [#17](https://github.com/claudio4ndres/inventario-el-portal/issues/17) |

## Causa → control → objetivo

Todas las causas dentro del alcance tienen al menos un control implementado y una HU que lo construye.

| Causa | Control | Objetivo | HU que lo implementa | Estado |
|-------|---------|----------|----------------------|--------|
| C4 Ventas e inventario no integrados (principal) | K1, K6 | OE1 | HU04, HU14 | Resuelta |
| C2 Sin punto de reorden (principal) | K3 | OE2 | HU02, HU06, HU10, HU11 | Resuelta |
| C1 Registro manual en cuaderno | K1, K2 | OE1 | HU03, HU04, HU09, HU11, HU16 | Resuelta |
| C3 Sin conteos cíclicos | K6 | OE1, OE4 | HU14 | Resuelta |
| C5 Datos desactualizados | K1 | OE1 | HU04 | Resuelta |
| C6 Sin trazabilidad ni reportes | K7, K8 | OE4 | HU07, HU15 | Resuelta |
| C7 Errores de digitación | K5 | OE1 | HU05, HU16 | Resuelta |
| C10 Sin control de vencimientos | K2, K4 | OE3 | HU03, HU12 | Resuelta |
| C11 Sin clasificación ABC | K3, K6, K7 | OE2, OE4 | HU02, HU17 | Resuelta |
| C12 Mermas no registradas | K7 | OE3 | HU13 | Resuelta |
| C8 Dependencia de una sola persona | K8 | OE5 | HU01, HU08 | Mitigada (fuera de alcance) |
| C9 Falta de capacitación | Manual de usuario | OE5 | — | Mitigada; capacitación afectada por CC-05 |

## Requerimientos no funcionales

| RNF | Cómo se cumple | Evidencia |
|-----|----------------|-----------|
| RNF1 Lector de código de barras | El lector USB funciona como teclado; la búsqueda por código exacto agrega el producto (HU05) | `ProductoTest`, prueba manual con lector (issue #27) |
| RNF2 Seguridad y bitácora | Contraseñas con hash, middleware `rol`, bitácora de movimientos con usuario | `AutenticacionTest`, `MatrizAccesoTest`, `AlertaYBitacoraTest` |
| RNF3 Respaldo diario | Pendiente (issue #49) | — |
| RNF4 PHP con soporte vigente | PHP 8.4 y PHPUnit 13; la CI verifica las versiones (CC-04) | Workflow `tests.yml`, PR #43 |

## Pruebas que cruzan varias HU

- `FlujoIntegracionTest`: cuatro flujos de punta a punta (abastecimiento, venta FEFO, control de pérdidas, clasificación y reorden automáticos).
- `MatrizAccesoTest`: cada rol entra solo a sus pantallas; un invitado es enviado al login.
- `DespliegueTest` y `SeederTest`: preparación de producción y datos de demostración.
