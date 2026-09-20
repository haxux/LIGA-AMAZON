#!/bin/sh
#
# Arranque del contenedor en Vercel.
#
set -e

# Si alguno de estos comandos falla, el contenedor no arranca. Es deliberado:
# un fallo ruidoso al desplegar es preferible a un sitio en pie sirviendo
# errores 500 a los visitantes.
#
# config:cache es lo unico que queda aqui de los tres cacheos: congela el valor
# de cada env() en el momento de ejecutarse, y durante el build no existen
# todavia ni APP_KEY ni las credenciales de la base de datos. Hornearlo en la
# imagen desplegaria una aplicacion apuntando a la nada.
#
# route:cache y view:cache, en cambio, no leen entorno alguno (las rutas no
# usan env() y el panel vive en un path fijo), asi que se hornean en la imagen
# y no se repiten en cada arranque en frio. Ver §5.2 de DESPLIEGUE.md.
php artisan config:cache

# §5.1 de DESPLIEGUE.md: las migraciones se aplican al arrancar, no a mano.
#
# --isolated toma un cerrojo atomico en el almacen de cache. En produccion
# CACHE_STORE=database, asi que el cerrojo vive en la propia base y vale entre
# instancias: si el host arranca varias a la vez, una migra y las demas siguen
# sin tocar el esquema.
#
# La rama sin cerrojo existe solo para la base recien creada: sin tabla
# cache_locks no hay cerrojo que tomar, y migrate:status es la forma barata de
# detectarlo, porque falla cuando ni siquiera existe la tabla migrations.
if php artisan migrate:status >/dev/null 2>&1; then
    php artisan migrate --force --isolated
else
    php artisan migrate --force
fi

exec frankenphp run --config /etc/frankenphp/Caddyfile
