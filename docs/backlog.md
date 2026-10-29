# Backlog del producto · Inventario El Portal

Este archivo es la **fuente de verdad del avance**. La rutina diaria de desarrollo lo lee para saber qué historia sigue y lo actualiza al terminar.

**Reglas**
- Se trabaja **una historia por día**, en el orden de la tabla: la primera con estado `pendiente` cuyas dependencias estén `hecha`.
- Una historia se marca `hecha` solo cuando su rama `feature/*` está integrada en `develop` con la CI en verde.
- Si una historia queda bloqueada, se marca `bloqueada` y se explica en **Notas**.

| Orden | HU | Historia | Prioridad | Depende de | Estado |
|------:|----|----------|-----------|------------|--------|
| 1 | HU01 | Iniciar sesión por rol | Must | — | hecha |
| 2 | HU02 | Gestionar productos | Must | — | hecha |
| 3 | HU03 | Recepción con lote y vencimiento | Must | — | hecha |
| 4 | HU04 | Venta con descuento de stock por lote | Must | — | hecha |
| 5 | HU05 | Búsqueda por código de barras | Must | — | hecha |
| 6 | HU06 | Alertas de punto de reorden | Must | — | hecha |
| 7 | HU07 | Bitácora de movimientos | Must | — | hecha |
| 8 | HU08 | Gestionar usuarios y roles | Should | HU01 | hecha |
| 9 | HU09 | Gestionar proveedores | Should | — | hecha |
| 10 | HU12 | Alertas de lotes por vencer (FEFO) | Should | HU03 | hecha |
| 11 | HU13 | Registrar mermas con cantidad, costo y motivo | Should | HU03 | hecha |
| 12 | HU10 | Calcular punto de reorden automáticamente | Should | HU04 | hecha |
| 13 | HU11 | Orden de compra sugerida y recepción contra OC | Should | HU09, HU10 | hecha |
| 14 | HU14 | Conteo cíclico por clase ABC y ajustes | Should | HU07 | hecha |
| 15 | HU15 | Tablero con al menos 4 indicadores | Should | HU13, HU14 | hecha |
| 16 | HU16 | Importar catálogo inicial desde CSV | Should | HU02 | hecha |
| 17 | HU17 | Clasificación ABC automática | Could | HU04 | hecha |

## Criterios de aceptación de las historias pendientes

**HU08 · Gestionar usuarios (administrador · RF9 · K8)**
- CRUD de usuarios con rol (administrador, vendedor o bodeguero) y opción de activar o desactivar.
- No se eliminan usuarios con movimientos registrados.
- El correo es único y la contraseña tiene al menos 8 caracteres.
- Un administrador no puede desactivarse a sí mismo.
- Solo el rol administrador accede a esta sección.

**HU09 · Gestionar proveedores (administrador · RF3 · K2)**
- CRUD de proveedores con RUT chileno único y validado (dígito verificador, módulo 11) y opción de activar o desactivar.
- Un proveedor inactivo no aparece en la recepción.

**HU12 · Lotes por vencer (administrador y bodeguero · RF5 · K4 · OE3)**
- Lista de los lotes con saldo mayor que 0 que vencen dentro de N días (por defecto 7, configurable), ordenados por fecha de vencimiento.
- Muestra días restantes, saldo y valor en riesgo (saldo × costo unitario).
- Los lotes ya vencidos aparecen destacados.
- El panel muestra cuántos lotes están por vencer.

**HU13 · Registrar mermas (bodeguero y administrador · RF6 · K7 · OE3)**
- Formulario con lote, cantidad (de 1 al saldo del lote), motivo (vencimiento, daño, robo u otro) y observación.
- Descuenta del lote y registra un movimiento MERMA con el costo, dentro de una transacción.
- Se agrega la columna `costo_total` o se calcula a partir del lote.
- No se puede registrar una merma mayor que el saldo del lote.

**HU10 · Punto de reorden calculado (administrador · RF4 · K3 · OE2)**
- Fórmula: punto de reorden = demanda diaria promedio (ventas de los últimos 28 días ÷ 28) × días de reposición + stock de seguridad.
- Días de reposición y stock de seguridad son campos del producto.
- El cálculo se ejecuta con un comando de Artisan programable (`inventario:recalcular-reorden`) y un botón "Recalcular".
- Solo se recalculan los productos con al menos una venta en el período.

**HU11 · Orden de compra sugerida (administrador · RF4/RF3 · K3/K2 · OE2)**
- Tablas `ordenes_compra` y `detalle_ordenes_compra`.
- Desde las alertas se genera una OC sugerida por proveedor, con cantidad = punto de reorden × 2 − stock (editable).
- Estados de la OC: borrador, enviada, recibida.
- La recepción puede asociarse a una OC y muestra las diferencias de cantidad.

**HU14 · Conteo cíclico (bodeguero · RF7 · K6 · OE1 M1.2)**
- Se programa por clase: A semanal, B quincenal, C mensual.
- Se registra el conteo físico; cada diferencia genera un movimiento AJUSTE con motivo.
- Se calcula la exactitud del inventario: productos con diferencia ÷ productos contados.

**HU15 · Tablero de indicadores (administrador · RF8 · K7 · OE4)**
- Al menos 4 indicadores actualizados en las últimas 24 horas:
  - M1.1: % de ventas con descuento automático.
  - M1.2: exactitud de inventario.
  - M2: quiebres de productos de alta rotación en las últimas 4 semanas.
  - M3: merma por vencimiento sobre las ventas.
- Gráfico simple de quiebres por semana.

**HU16 · Importar catálogo CSV (administrador · OE5 M5.1)**
- Carga de un CSV con código de barras, nombre, precio, punto de reorden y clase ABC.
- Muestra una vista previa con los errores fila por fila; las filas válidas se importan en una transacción.
- Si el código ya existe, se actualiza el producto.
- Plantilla CSV descargable.

**HU17 · Clasificación ABC automática (administrador · RF8 · K6/K7 · OE4)**
- Clasificación por valor vendido en los últimos 90 días: A hasta el 80 % acumulado, B hasta el 95 % y C el resto.
- Comando de Artisan y botón "Recalcular ABC".

## Notas

- 20-10-2026: backlog creado. MPV v0.1.0 integrado en `main` (33 pruebas en verde).
- 20-10-2026: HU08 (gestionar usuarios y roles) integrada en `develop` con la CI en verde en SQLite y MySQL 8.4 (49 pruebas en total; 16 nuevas).
- 21-10-2026: HU09 (gestionar proveedores con RUT validado por módulo 11) integrada en `develop` con la CI en verde en SQLite y MySQL 8.4 (70 pruebas en total; 21 nuevas). Sin cambios de esquema.
- 22-10-2026: HU12 (alertas de lotes por vencer, orden FEFO, ventana configurable de 7 días por defecto) integrada en `develop` con la CI en verde en SQLite y MySQL 8.4 (79 pruebas en total; 9 nuevas). Sin cambios de esquema.
- 23-10-2026: HU13 (registro de mermas por lote con cantidad, costo y motivo) integrada en `develop` con la CI en verde en SQLite y MySQL 8.4 (90 pruebas en total; 11 nuevas). Nueva migración: `movimientos.costo_total` y `movimientos.motivo_merma`.
- 24-10-2026: HU10 (punto de reorden calculado con la demanda de 28 días, días de reposición y stock de seguridad; comando `inventario:recalcular-reorden` programado a diario y botón "Recalcular") integrada en `develop` con la CI en verde en SQLite y MySQL 8.4 (100 pruebas en total; 10 nuevas). Nueva migración: `productos.dias_reposicion` y `productos.stock_seguridad`.
- 25-10-2026: HU11 (orden de compra sugerida por proveedor desde las alertas, cantidad = punto de reorden × 2 − stock editable en borrador, estados borrador/enviada/recibida y recepción contra OC con diferencias de cantidad) integrada en `develop` con la CI en verde en SQLite y MySQL 8.4 (112 pruebas en total; 12 nuevas). Nueva migración: tablas `ordenes_compra` y `detalle_ordenes_compra`, y columna `recepciones.orden_compra_id`.
- 26-10-2026: HU14 (conteo cíclico por clase ABC: A semanal, B quincenal y C mensual; cada diferencia exige motivo y genera movimientos AJUSTE, el faltante en orden FEFO y el sobrante en el lote más reciente; exactitud por conteo y de los últimos 30 días) integrada en `develop` con la CI en verde en SQLite y MySQL 8.4 (121 pruebas en total; 9 nuevas). Nueva migración: tablas `conteos` y `detalle_conteos`, y columna `movimientos.detalle_conteo_id`.
- 27-10-2026: HU15 (tablero de indicadores para el administrador: M1.1 ventas con descuento automático por lote, M1.2 exactitud de los conteos de 30 días, M2 quiebres de productos clase A en 4 semanas con gráfico semanal y M3 merma por vencimiento a costo sobre las ventas; calculados al consultar, sin caché) integrada en `develop` con la CI en verde en SQLite y MySQL 8.4 (128 pruebas en total; 7 nuevas). Sin cambios de esquema.
- 28-10-2026: HU16 (importación del catálogo inicial desde CSV con separador punto y coma o coma, vista previa con errores fila por fila, códigos repetidos en el archivo detectados, filas válidas importadas en una transacción y actualización del producto si el código ya existe; plantilla descargable) integrada en `develop` con la CI en verde en SQLite y MySQL 8.4 (138 pruebas en total; 10 nuevas). Sin cambios de esquema.
- 29-10-2026: HU17 (clasificación ABC automática por valor vendido en los últimos 90 días: A hasta el 80 % acumulado, B hasta el 95 % y C el resto, con el producto que cruza el umbral en la clase superior; sin ventas no se modifica nada; comando `inventario:recalcular-abc` y botón "Recalcular ABC") integrada en `develop` con la CI en verde en SQLite y MySQL 8.4 (150 pruebas en total; 12 nuevas). Sin cambios de esquema. Backlog completo: MPV listo para el hito H4.
