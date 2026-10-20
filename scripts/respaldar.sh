#!/usr/bin/env bash
#
# Copia de seguridad de producción. Corre EN EL SERVIDOR, desde el cron de
# hPanel (ese hosting no trae el binario `crontab`, así que la programación se
# añade a mano una vez; la receta está en DESPLIEGUE.md §6).
#
# Guarda dos piezas por copia, que juntas son un momento entero de la liga:
#   - la base de datos comprimida
#   - las subidas (escudos, fotos de jugadores, imágenes de noticias)
#
# Uso:  scripts/respaldar.sh [etiqueta]
# La etiqueta sólo cambia el nombre del fichero, para distinguir una copia
# hecha a mano antes de tocar algo ("antes-de-cesiones") de las semanales.
#
set -euo pipefail

# El cron arranca con un entorno pelado: ni PATH completo ni HOME garantizado.
export PATH="/usr/local/bin:/usr/bin:/bin"

APP="$(cd "$(dirname "$0")/.." && pwd)"
DESTINO="$(cd "$APP/../../.." && pwd)/respaldos"
RETENER=8          # ocho semanas de copias semanales: unos dos meses
ETIQUETA="${1:-semanal}"

mkdir -p "$DESTINO"
REGISTRO="$DESTINO/respaldar.log"

decir() { printf '%s  %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$1" | tee -a "$REGISTRO"; }
morir() { decir "FALLO: $1"; exit 1; }

sello="$(date +%Y%m%d-%H%M)"
base="$DESTINO/$ETIQUETA-$sello"

# Los valores del .env vienen entrecomillados, y no siempre con el mismo tipo
# de comilla: una copia salió de 20 bytes porque el `sed` de turno quitaba sólo
# las dobles y ese día eran simples. Se quitan las dos.
valor_env() {
    grep -m1 "^$1=" "$APP/.env" | cut -d= -f2- | tr -d "\"'"
}

BD="$(valor_env DB_DATABASE)"
USUARIO="$(valor_env DB_USERNAME)"
CLAVE="$(valor_env DB_PASSWORD)"
[ -n "$BD" ] && [ -n "$USUARIO" ] || morir "no encuentro las credenciales en $APP/.env"

decir "== copia '$ETIQUETA' a $DESTINO"

# --single-transaction: el volcado es coherente sin bloquear la liga mientras
# alguien navega. --hex-blob y utf8mb4 para que los nombres con acentos y los
# binarios vuelvan tal cual.
MYSQL_PWD="$CLAVE" mysqldump -h localhost -u "$USUARIO" \
    --single-transaction --default-character-set=utf8mb4 --complete-insert --hex-blob \
    "$BD" | gzip -9 > "$base.sql.gz" || morir "mysqldump no terminó"

# Un volcado truncado se comprime igual de bien y pesa lo suyo: parece correcto.
# La única prueba de que está entero es que mysqldump haya firmado el final, y
# se comprueba sobre el fichero YA escrito, no sobre la tubería.
gunzip -c "$base.sql.gz" | tail -5 | grep -q 'Dump completed' \
    || morir "el volcado está truncado ($base.sql.gz): no lleva la firma de cierre"

# El .env se queda fuera a propósito: lleva las claves en claro, y una copia que
# se baja a un portátil no es sitio para ellas. Para restaurar se rehace desde
# `.env.production.example` (DESPLIEGUE.md §2).
tar -czf "$base-archivos.tar.gz" -C "$APP" storage/app/public \
    || morir "no pude empaquetar las subidas"

decir "base de datos  $(du -h "$base.sql.gz" | cut -f1)"
decir "subidas        $(du -h "$base-archivos.tar.gz" | cut -f1)"

# La rotación borra SÓLO lo que generó este script con esta etiqueta. Las copias
# hechas a mano antes de una faena delicada tienen otro nombre y no se tocan:
# que una tarea automática barra la red de seguridad de alguien es inaceptable.
for patron in "$ETIQUETA-*.sql.gz" "$ETIQUETA-*-archivos.tar.gz"; do
    # shellcheck disable=SC2086
    sobrantes=$(ls -1t $DESTINO/$patron 2>/dev/null | tail -n +$((RETENER + 1)) || true)
    for viejo in $sobrantes; do
        decir "rotando: retiro $(basename "$viejo")"
        rm -f "$viejo"
    done
done

decir "== listo: $(basename "$base").{sql.gz,-archivos.tar.gz}"
