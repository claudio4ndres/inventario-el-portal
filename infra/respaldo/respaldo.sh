#!/usr/bin/env bash
# Respaldo diario de la base de datos (RNF3).
#
# Corre como servicio cron en Railway: se conecta a MySQL por la red privada,
# vuelca cada base de datos de la aplicación a un archivo .sql.gz en el volumen
# montado en RESPALDO_DIR y conserva los últimos RESPALDO_RETENCION archivos por base.
#
# Modos (argumento o variable RESPALDO_MODO):
#   respaldo   (por defecto) genera el respaldo y aplica la retención.
#   simulacro  genera un respaldo, lo restaura en una base temporal, compara el
#              número de filas de cada tabla con la original y borra la temporal.
#              Es la evidencia de que los respaldos se pueden restaurar.
#   listar     muestra los respaldos guardados.
#
# Variables requeridas: MYSQLHOST, MYSQLPORT, MYSQLUSER, MYSQLPASSWORD
# (en Railway se toman del servicio MySQL con ${{MySQL.MYSQLHOST}}, etc.).
set -euo pipefail

: "${MYSQLHOST:?falta MYSQLHOST}"
: "${MYSQLPORT:?falta MYSQLPORT}"
: "${MYSQLUSER:?falta MYSQLUSER}"
: "${MYSQLPASSWORD:?falta MYSQLPASSWORD}"

DESTINO="${RESPALDO_DIR:-/respaldos}"
RETENCION="${RESPALDO_RETENCION:-7}"
MODO="${1:-${RESPALDO_MODO:-respaldo}}"
PREFIJO_SIMULACRO="simulacro_"

log() { echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] $*"; }

# La contraseña va en un archivo temporal y no en la línea de comandos,
# para que no aparezca en la lista de procesos.
CNF="$(mktemp)"
chmod 600 "$CNF"
trap 'rm -f "$CNF"' EXIT
cat >"$CNF" <<EOF
[client]
host=${MYSQLHOST}
port=${MYSQLPORT}
user=${MYSQLUSER}
password=${MYSQLPASSWORD}
EOF

sql() { mysql --defaults-extra-file="$CNF" -N -B "$@"; }

# Bases de la aplicación: todas menos las del sistema y las temporales del simulacro.
bases() {
    sql -e "SELECT schema_name FROM information_schema.schemata
            WHERE schema_name NOT IN ('mysql','information_schema','performance_schema','sys')
              AND LEFT(schema_name, ${#PREFIJO_SIMULACRO}) <> '${PREFIJO_SIMULACRO}'
            ORDER BY schema_name"
}

tablas() {
    sql -e "SELECT table_name FROM information_schema.tables
            WHERE table_schema = '$1' AND table_type = 'BASE TABLE'
            ORDER BY table_name"
}

# --set-gtid-purged solo existe en el mysqldump de MySQL (no en el de MariaDB).
OPCIONES_DUMP=(--single-transaction --routines --triggers --events --no-tablespaces)
if mysqldump --help 2>/dev/null | grep -q -- '--set-gtid-purged'; then
    OPCIONES_DUMP+=(--set-gtid-purged=OFF)
fi

ultimo_respaldo() {
    # sed lee toda la entrada, así que no corta la tubería (pipefail).
    ls -1t "$DESTINO/$1-"*.sql.gz 2>/dev/null | sed -n 1p || true
}

respaldar() {
    mkdir -p "$DESTINO"
    local marca db archivo
    marca="$(date -u +%Y%m%dT%H%M%SZ)"
    local lista
    lista="$(bases)"
    if [ -z "$lista" ]; then
        log "ERROR: no hay bases de datos de la aplicación para respaldar"
        exit 1
    fi
    for db in $lista; do
        archivo="$DESTINO/$db-$marca.sql.gz"
        mysqldump --defaults-extra-file="$CNF" "${OPCIONES_DUMP[@]}" "$db" | gzip -9 >"$archivo.tmp"
        gzip -t "$archivo.tmp"
        # mysqldump escribe "-- Dump completed" solo si terminó sin errores.
        if ! zcat "$archivo.tmp" | tail -n 1 | grep -q 'Dump completed'; then
            rm -f "$archivo.tmp"
            log "ERROR: el volcado de $db quedó incompleto"
            exit 1
        fi
        mv "$archivo.tmp" "$archivo"
        log "OK respaldo de $db: $(basename "$archivo") ($(du -h "$archivo" | cut -f1))"

        # Retención: se conservan los RETENCION más recientes de esta base.
        ls -1t "$DESTINO/$db-"*.sql.gz | tail -n +"$((RETENCION + 1))" | while read -r viejo; do
            rm -f -- "$viejo"
            log "Eliminado por retención: $(basename "$viejo")"
        done
    done
}

simulacro() {
    respaldar
    local db archivo temporal tabla original restaurada diferencias=0
    for db in $(bases); do
        archivo="$(ultimo_respaldo "$db")"
        temporal="${PREFIJO_SIMULACRO}${db}"
        log "Simulacro: restaurando $(basename "$archivo") en $temporal"
        sql -e "DROP DATABASE IF EXISTS \`$temporal\`; CREATE DATABASE \`$temporal\`"
        zcat "$archivo" | mysql --defaults-extra-file="$CNF" "$temporal"
        for tabla in $(tablas "$db"); do
            original="$(sql -e "SELECT COUNT(*) FROM \`$db\`.\`$tabla\`")"
            restaurada="$(sql -e "SELECT COUNT(*) FROM \`$temporal\`.\`$tabla\`" 2>/dev/null || echo 'falta')"
            if [ "$original" = "$restaurada" ]; then
                log "  $db.$tabla: $original filas = $restaurada"
            else
                log "  DIFERENCIA $db.$tabla: original $original, restaurada $restaurada"
                diferencias=$((diferencias + 1))
            fi
        done
        sql -e "DROP DATABASE \`$temporal\`"
    done
    if [ "$diferencias" -gt 0 ]; then
        log "SIMULACRO FALLIDO: $diferencias tablas con diferencias"
        exit 1
    fi
    log "SIMULACRO OK: todas las tablas restauradas coinciden con la base original"
}

listar() {
    ls -lh "$DESTINO" 2>/dev/null || log "No hay respaldos en $DESTINO"
}

case "$MODO" in
    respaldo) respaldar ;;
    simulacro) simulacro ;;
    listar) listar ;;
    *) log "Modo desconocido: $MODO (use respaldo, simulacro o listar)"; exit 2 ;;
esac
