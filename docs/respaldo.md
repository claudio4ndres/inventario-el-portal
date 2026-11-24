# Respaldo y recuperación (RNF3)

**Requerimiento:** RNF3, respaldo diario de la base de datos.
**Decisión (24-11-2026):** se usan los respaldos programados del volumen de MySQL que ofrece Railway. Se eligió esta opción en vez de un comando `mysqldump` propio porque no agrega código que mantener, los respaldos son incrementales y la restauración se hace desde el panel.

## Qué se respalda

| Elemento | Valor |
|----------|-------|
| Proyecto Railway | `inventario-el-portal`, ambiente `production` |
| Servicio | `MySQL` (imagen `mysql:8`, región `sfo`) |
| Volumen | `mysql-volume` (5 GB), montado en `/var/lib/mysql` |
| Contenido | La instancia MySQL completa: todas las bases de datos del proyecto |

Los servicios `web` y `web-pruebas` no guardan datos en disco, así que no necesitan respaldo: se reconstruyen desde Git (tags y ramas) y desde la imagen Docker.

## Programación

| Calendario | Frecuencia | Retención |
|------------|-----------|-----------|
| Diario (cumple RNF3) | cada 24 horas | 6 días |
| Semanal | cada 7 días | 27 días |

La retención la fija Railway. Los respaldos son incrementales (copy-on-write) y se cobran por GB a la tarifa de los volúmenes.

### Cómo se activa (una vez)

1. Panel de Railway → proyecto `inventario-el-portal` → servicio **MySQL** → pestaña **Backups**.
2. Marcar los calendarios **Daily** y **Weekly**.
3. Crear un respaldo manual inicial con **Backup now**. Queda como punto de partida de la LB-5.

## Restauración

1. Servicio **MySQL** → **Backups** → elegir el respaldo por su fecha → **Restore**.
2. Railway deja el cambio en espera de revisión: **Details** en la parte superior del lienzo del proyecto.
3. Se crea un volumen nuevo con la fecha del respaldo, montado en `/var/lib/mysql`. El volumen original queda en el proyecto, sin montar y con su nombre.
4. **Deploy** para aplicar. MySQL se reinicia con los datos restaurados.
5. Verificar desde la aplicación: iniciar sesión, revisar el inventario y el tablero de indicadores.

**Para deshacer** una restauración, se vuelve a montar el volumen original en `/var/lib/mysql`.

## Limitaciones conocidas

- **Consistencia:** el respaldo es una instantánea del disco con MySQL en funcionamiento. InnoDB la recupera con su registro de transacciones (*redo log*), igual que después de un corte de energía. Por eso la restauración se valida con el simulacro de abajo y no se da por supuesta.
- **Mismo proyecto y ambiente:** un respaldo solo se puede restaurar en el mismo proyecto y ambiente de Railway. Si se pierde la cuenta o el proyecto, se pierden también los respaldos. Como resguardo fuera de Railway, se recomienda guardar antes de cada entrega del informe una exportación `mysqldump` en Google Drive (`/Proyecto/Respaldos`).
- **No vaciar el volumen:** la opción *Wipe volume* borra también todos sus respaldos.

## Simulacro de restauración (evidencia de RNF3)

Se registra una vez antes del cierre de H5, en la issue #49:

| Paso | Resultado esperado |
|------|--------------------|
| 1. Anotar el número de productos y el total vendido del tablero | Valores de referencia |
| 2. Crear un respaldo manual | Aparece en la pestaña **Backups** con su fecha |
| 3. Registrar una venta de prueba | El total vendido cambia |
| 4. Restaurar el respaldo del paso 2 y desplegar | MySQL se reinicia sin errores en los registros |
| 5. Revisar el tablero | Vuelven los valores del paso 1 y la venta de prueba ya no está |

Fuente: documentación de Railway, [Backups](https://docs.railway.com/volumes/backups).
