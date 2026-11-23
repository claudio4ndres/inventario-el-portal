# Escenario de prueba simulado (28 días)

`database/seeders/EscenarioPruebaSeeder.php` carga 28 días de operación de un almacén **ficticio**
para probar que el tablero (M1.1, M1.2, M2, M3) y las alertas responden a casos conocidos.

> Son datos de prueba del sistema, **no** datos del almacén El Portal ni resultados del piloto.
> Proveedores y usuarios llevan la marca "(simulado)" / "(pruebas)".

## Casos que contiene

| Caso | Cuándo | Indicador que lo muestra |
|---|---|---|
| Proveedor de lácteos no entrega la leche | Días 10 y 12 (semana 2) | M2: quiebres de la leche |
| Reposición semanal justa en fideos y arroz | Todo el período | M2: quiebres recurrentes |
| Perecibles que vencen sin venderse (yogur, queso, cecina) | Retiro al cierre de cada día | M3: merma por vencimiento |
| Bandeja de huevos caída | Día 15 | Merma por daño |
| Bebida faltante en góndola | Día 17 | Merma por robo |
| Conteo semanal de la clase A | Días 6, 13, 20 y 27 (el día 13 falta una bebida) | M1.2: exactitud < 100 % |

El escenario es determinista (semilla fija) y solo se carga sobre una base vacía.

## Valores esperados en el tablero

Con el escenario recién cargado, el tablero de indicadores debe mostrar exactamente estos valores.
La prueba `EscenarioPruebaTest::test_los_indicadores_del_escenario_tienen_los_valores_esperados`
los verifica en la CI (SQLite y MySQL 8.4), por lo que sirven de referencia para la validación de H5 (CC-05).

| Indicador | Valor esperado | Cómo se obtiene |
|---|---|---|
| M1.1 Ventas con descuento automático | **100 %** (434 de 434 ventas) | Todas las ventas descuentan stock por lote |
| M1.2 Exactitud del inventario (30 días) | **91,7 %** | 4 conteos de la clase A; 1 diferencia (bebida, día 13) en 12 productos contados |
| M2 Quiebres de alta rotación (4 semanas) | **20** días-producto sin stock | Por semana: 4 · **9** · 4 · 3 (la semana 2 tiene las entregas de leche que no llegaron) |
| M2 por producto | Fideos 11 · Leche 5 · Pan de molde 4 | Reposición justa en fideos; leche sin entrega los días 10 y 12 |
| M3 Merma por vencimiento | **1,1 %** ($19.460 sobre $1.801.210 vendidos) | 10 retiros de perecibles vencidos (63 unidades) |
| Otras mermas | Daño $2.400 · Robo $1.200 | Huevos (día 15) y bebida (día 17); no cuentan en M3 |

Los montos dependen solo de la semilla fija (`SEMILLA = 2026`), no de la fecha en que se cargue el escenario.

## Cómo cargarlo

- **Local con Docker:** en `docker-compose.yml` activar `DEMO_SEEDER: "EscenarioPruebaSeeder"` y `docker compose up --build`.
- **Local sin Docker:** `php artisan migrate:fresh --seed --seeder=EscenarioPruebaSeeder`.
- **Ambiente de pruebas en Railway:** variables `DEMO_RESET=true` y `DEMO_SEEDER=EscenarioPruebaSeeder`.

**Nunca en producción:** producción usa `DEMO_RESET=false`, que no borra ni siembra datos.
Si se intentara correr el seeder sobre una base con usuarios, se detiene con un error.

Usuarios: `admin@elportal.test`, `caja@elportal.test`, `bodega@elportal.test` (contraseña `ElPortal2026`).
