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

## Cómo cargarlo

- **Local con Docker:** en `docker-compose.yml` activar `DEMO_SEEDER: "EscenarioPruebaSeeder"` y `docker compose up --build`.
- **Local sin Docker:** `php artisan migrate:fresh --seed --seeder=EscenarioPruebaSeeder`.
- **Ambiente de pruebas en Railway:** variables `DEMO_RESET=true` y `DEMO_SEEDER=EscenarioPruebaSeeder`.

**Nunca en producción:** producción usa `DEMO_RESET=false`, que no borra ni siembra datos.
Si se intentara correr el seeder sobre una base con usuarios, se detiene con un error.

Usuarios: `admin@elportal.test`, `caja@elportal.test`, `bodega@elportal.test` (contraseña `ElPortal2026`).
