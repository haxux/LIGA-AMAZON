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
# Los tres intentos son por el escalado a cero: el contenedor arranca muchas
# veces al dia, y sin ellos un parpadeo de la base gestionada durante un
# arranque en frio no seria una pagina con error, seria el sitio entero caido
# hasta el siguiente intento. Agotados los tres, se sale con error a
# proposito: servir codigo nuevo contra un esquema viejo falla en silencio.
#
# Cada intento va dentro de un `if`: con `set -e`, un `cmd && break` que falla
# mata el script en el primer intento y no queda reintento ninguno.
attempt=1
migrated=0
while [ "$attempt" -le 3 ]; do
    if php artisan migrate:status >/dev/null 2>&1; then
        if php artisan migrate --force --isolated; then
            migrated=1
            break
        fi
    else
        # Base recien creada: sin tabla cache_locks no hay cerrojo que tomar.
        # migrate:status es la forma barata de detectarlo, porque falla cuando
        # ni siquiera existe la tabla migrations.
        if php artisan migrate --force; then
            migrated=1
            break
        fi
    fi

    echo "entrypoint: migraciones fallidas (intento ${attempt}/3)" >&2
    attempt=$((attempt + 1))
    sleep 2
done

if [ "$migrated" -ne 1 ]; then
    echo "entrypoint: no se pudieron aplicar las migraciones" >&2
    exit 1
fi

exec frankenphp run --config /etc/frankenphp/Caddyfile
