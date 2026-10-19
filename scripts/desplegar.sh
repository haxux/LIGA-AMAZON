#!/usr/bin/env bash
#
# Despliega a producción (lietoernooien.com) la receta entera de DESPLIEGUE.md §5,
# con las trampas que nos han mordido ya metidas dentro:
#
#   - `view:clear` ANTES de compilar: el CSS escanea el caché de Blade compilado,
#     así que sin esto se cuelan las clases del panel de Filament en la hoja
#     pública (§3). Medido: 61 kB contra 44.
#   - `--no-scripts` y `package:discover` aparte: ese PHP tiene `proc_open`
#     deshabilitado, y Composer muere al lanzar el script posterior DESPUÉS de
#     generar el autoload, así que el despliegue parece completo.
#   - El seeder NO se corre nunca: sobre una liga en marcha duplica a los
#     jugadores traspasados.
#
# Uso:  scripts/desplegar.sh [--sin-pruebas]
#
set -euo pipefail

CLAVE=~/.ssh/liga_hostinger
PUERTO=65002
REMOTO=u269987465@109.106.251.174
RUTA=~/domains/lietoernooien.com/app
PHP=/opt/alt/php84/usr/bin/php
SITIO=https://lietoernooien.com

paso() { printf '\n\033[1m==> %s\033[0m\n' "$1"; }
en_servidor() { ssh -i "$CLAVE" -p "$PUERTO" "$REMOTO" "cd $RUTA && $1"; }

cd "$(dirname "$0")/.."

if [ -n "$(git status --porcelain)" ]; then
    echo "Hay cambios sin commitear. Commitea o guarda antes de desplegar." >&2
    git status --short >&2
    exit 1
fi

git fetch origin --quiet
if [ "$(git rev-parse HEAD)" != "$(git rev-parse origin/main)" ]; then
    echo "Tu rama y origin/main no coinciden. Sube o baja los commits primero." >&2
    exit 1
fi

if [ "${1:-}" != "--sin-pruebas" ]; then
    paso "Suite completa"
    docker compose exec -T app php artisan test
fi

paso "Compilando assets en limpio"
docker compose exec -T app php artisan view:clear
docker compose run --rm -T node npm run build

paso "Subiendo assets"
rsync -az --delete -e "ssh -i $CLAVE -p $PUERTO" public/build/ "$REMOTO:$RUTA/public/build/"

paso "Desplegando el código"
en_servidor "git pull --ff-only"
en_servidor "$PHP /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction --no-scripts"
en_servidor "$PHP artisan package:discover"
en_servidor "$PHP artisan migrate --force"
en_servidor "$PHP artisan config:cache && $PHP artisan route:cache && $PHP artisan view:cache"

paso "Comprobando el sitio"
for ruta in / /clasificacion /partidos /copas /estadisticas /equipos /noticias /historia; do
    codigo=$(curl -s -o /dev/null -w '%{http_code}' "$SITIO$ruta")
    printf '  %-16s HTTP %s\n' "$ruta" "$codigo"
    [ "$codigo" = "200" ] || { echo "  ^^ esa página no responde 200" >&2; exit 1; }
done

hoja=$(curl -s "$SITIO/" | grep -oE '/build/assets/app-[^"]+\.css' | head -1)
printf '  hoja de estilos %s -> HTTP %s\n' "$hoja" "$(curl -s -o /dev/null -w '%{http_code}' "$SITIO$hoja")"

paso "Desplegado: $(en_servidor 'git log --oneline -1')"
