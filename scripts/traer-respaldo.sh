#!/usr/bin/env bash
#
# Baja de producción la copia de seguridad más reciente.
#
# El cron de hPanel deja las copias en el servidor, y eso cubre el susto normal
# (un borrado, un despliegue que sale mal). No cubre el gordo: si se pierde la
# cuenta, se pierden el original y las copias a la vez, porque viven en el mismo
# disco. Para eso hace falta sacarlas de ahí, y eso lo hace esta máquina.
#
# Uso:  scripts/traer-respaldo.sh [destino]
# Destino por omisión: ~/respaldos-liga
#
set -euo pipefail

CONFIG="$(dirname "$0")/produccion.env"
[ -f "$CONFIG" ] || { echo "Falta $CONFIG. Cópialo de produccion.env.ejemplo." >&2; exit 1; }
# shellcheck source=/dev/null
. "$CONFIG"
for v in CLAVE PUERTO REMOTO RUTA; do
    [ -n "${!v:-}" ] || { echo "Falta $v en $CONFIG" >&2; exit 1; }
done

DESTINO="${1:-$HOME/respaldos-liga}"
mkdir -p "$DESTINO"

remoto() { ssh -i "$CLAVE" -p "$PUERTO" "$REMOTO" "$1"; }

# El `cd` es para que la tilde la expanda el shell del servidor: en esta máquina
# apuntaría a otro home. Es la misma trampa que tumbó el despliegue.
ultimo=$(remoto "cd $RUTA/../../.. && ls -1t respaldos/semanal-*.sql.gz 2>/dev/null | head -1")
[ -n "$ultimo" ] || { echo "No hay ninguna copia semanal en el servidor todavía." >&2; exit 1; }

pareja="${ultimo%.sql.gz}-archivos.tar.gz"
echo "Última copia en el servidor: $(basename "$ultimo")"

for pieza in "$ultimo" "$pareja"; do
    if remoto "cd $RUTA/../../.. && test -f '$pieza'"; then
        scp -q -i "$CLAVE" -P "$PUERTO" "$REMOTO:\$HOME/$pieza" "$DESTINO/"
        echo "  bajado  $(basename "$pieza")  $(du -h "$DESTINO/$(basename "$pieza")" | cut -f1)"
    else
        echo "  falta   $(basename "$pieza")  (la copia está incompleta)" >&2
    fi
done

# Mismo motivo que en el servidor: un fichero truncado se ve igual de sano desde
# fuera. Si la firma de cierre no está, la copia no sirve y hay que saberlo hoy,
# no el día que haga falta restaurar.
if gunzip -c "$DESTINO/$(basename "$ultimo")" | tail -5 | grep -q 'Dump completed'; then
    echo "Volcado verificado: lleva la firma de cierre de mysqldump."
else
    echo "CUIDADO: el volcado bajado está truncado. No sirve para restaurar." >&2
    exit 1
fi

echo "En $DESTINO"
