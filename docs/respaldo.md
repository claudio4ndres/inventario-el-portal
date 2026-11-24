# Respaldo y recuperación (RNF3)

**Requerimiento:** RNF3, respaldo diario de la base de datos.

**Decisión (24-11-2026):** un servicio cron propio en Railway, `respaldo-mysql`, hace el respaldo con `mysqldump`.

Antes se había elegido usar los respaldos de volumen de Railway (PR #59). Se descartaron porque en el plan actual el panel solo permite ver y restaurar respaldos ya existentes. Crear respaldos o programarlos exige el plan Pro.

## Cómo funciona

```
respaldo-mysql (cron 07:00 UTC) ──red privada──▶ MySQL (base "railway" de producción)
        │
        └─▶ volumen respaldos-volume: /respaldos/railway-AAAAMMDDTHHMMSSZ.sql.gz (últimos 7)
```

| Elemento | Valor |
|----------|-------|
| Servicio | `respaldo-mysql` (proyecto `inventario-el-portal`, ambiente `production`) |
| Código | [`infra/respaldo/`](../infra/respaldo): `respaldo.sh` y `Dockerfile` (cliente de `mysql:8.4`) |
| Configuración del servicio | En Railway: raíz `/infra/respaldo`, `Dockerfile`, cron `0 7 * * *`, reinicio `NEVER`, volumen en `/respaldos` (ver [`gestion-configuracion.md`](gestion-configuracion.md#configuración-de-railway)) |
| Horario | `0 7 * * *`: 07:00 UTC, que son las 04:00 en Chile en horario de verano y las 03:00 en invierno |
| Qué respalda | Cada base de la aplicación en el servidor MySQL. Hoy es una sola: `railway`, la que usa el servicio `web` |
| Dónde guarda | Volumen `respaldos-volume`, montado en `/respaldos`, separado del volumen de MySQL |
| Retención | Los 7 respaldos más recientes por base (`RESPALDO_RETENCION`) |
| Conexión | Red privada de Railway (`MYSQLHOST` = `${{MySQL.MYSQLHOST}}`). MySQL no se expone a internet |

`web-pruebas` no aparece en la tabla porque usa SQLite dentro de su propio contenedor y no la base de producción. Sus datos se rehacen con el seeder del escenario de prueba.

### Garantías del script

- **Respaldo consistente:** `--single-transaction` toma una foto consistente de las tablas InnoDB sin bloquear la aplicación.
- **Respaldo completo:** se comprueba que el `.gz` no esté dañado (`gzip -t`) y que el volcado termine con `-- Dump completed`. Si falla cualquiera de las dos cosas, el archivo se descarta y el servicio termina con error, visible en *Cron Runs*.
- **Contraseña protegida:** va en un archivo temporal con permisos `600` y no en la línea de comandos.
- **Acentos y emojis:** se conservan, porque el volcado se hace en `utf8mb4`.

## Modos

Se elige con la variable `RESPALDO_MODO` del servicio. Cada cambio de variable redespliega el servicio, y el despliegue ejecuta el modo de inmediato.

| Modo | Qué hace |
|------|----------|
| `respaldo` (normal) | Genera el respaldo y aplica la retención |
| `simulacro` | Genera un respaldo, lo restaura en una base temporal `simulacro_<base>`, compara las filas de cada tabla con la base original y borra la temporal. No toca los datos de producción |
| `restaurar` | Restaura `RESPALDO_ARCHIVO` en su base original. Antes hace un respaldo de seguridad para poder deshacer |
| `listar` | Muestra los respaldos guardados |

## Restauración

1. Ver los respaldos disponibles: dejar `RESPALDO_MODO=listar` y revisar los registros del servicio en *Cron Runs* → *View logs*.
2. Configurar `RESPALDO_MODO=restaurar` y `RESPALDO_ARCHIVO=railway-AAAAMMDDTHHMMSSZ.sql.gz`. El redespliegue ejecuta la restauración.
3. Revisar en los registros la línea `RESTAURACIÓN OK` y el nombre del respaldo de seguridad que se generó antes de restaurar.
4. Volver a dejar `RESPALDO_MODO=respaldo` y borrar `RESPALDO_ARCHIVO`.
5. Verificar en la aplicación: iniciar sesión, revisar el inventario y el tablero.

**Para deshacer:** restaurar el respaldo de seguridad del paso 3.

**Protección:** cada archivo se restaura una sola vez. Si `RESPALDO_MODO=restaurar` quedara configurado por olvido, el cron del día siguiente no vuelve a restaurar el mismo archivo. En ese caso hace un respaldo normal y deja un aviso en los registros.

Conviene restaurar fuera del horario de atención, porque durante unos segundos la base queda vacía mientras se carga.

## Limitaciones

- **Mismo proyecto:** los respaldos están en Railway, en el mismo proyecto que la base. Protegen contra errores de datos (un borrado o una importación equivocada), pero no contra la pérdida de la cuenta o del proyecto. Una copia fuera de Railway queda como mejora posterior, junto con el piloto real (CC-05).
- **Siete días:** con la retención de 7 respaldos diarios no se puede volver a un estado de hace más de una semana. Se puede aumentar con `RESPALDO_RETENCION`. Hoy cada respaldo pesa unos 8 KB, así que el volumen no es una restricción.

## Pruebas

**Antes del despliegue,** el script se probó contra MySQL 8.0 en local:

- respaldo y retención (con una retención de 2, el tercer respaldo eliminó el más antiguo);
- simulacro;
- restauración y deshacer;
- protección contra restaurar dos veces;
- texto con `ñ`, tildes, `☕` y `€`;
- errores: variable faltante, contraseña incorrecta, archivo inexistente y modo desconocido.

**En Railway (24-11-2026, evidencia de RNF3):** primer despliegue en modo `simulacro`:

```
OK respaldo de railway: railway-20261124T215721Z.sql.gz (8.0K)
Simulacro: restaurando railway-20261124T215721Z.sql.gz en simulacro_railway
  railway.lotes: 10 filas = 10
  railway.movimientos: 18 filas = 18
  railway.productos: 9 filas = 9
  railway.ventas: 2 filas = 2
  ... (21 tablas)
SIMULACRO OK: todas las tablas restauradas coinciden con la base original
```
