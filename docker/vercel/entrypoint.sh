#!/bin/sh
#
# Arranque del contenedor en Vercel.
#
set -e

# El cacheado se hace aquí y no en el Dockerfile a propósito: config:cache
# congela el valor de cada env() en el momento en que se ejecuta, y durante el
# build no existen todavía ni APP_KEY ni las credenciales de la base de datos.
# Hornearlas en la imagen desplegaría una aplicación apuntando a la nada.
#
# Si alguno de estos comandos falla, el contenedor no arranca. Es deliberado:
# un fallo ruidoso al desplegar es preferible a un sitio en pie sirviendo
# errores 500 a los visitantes.
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec frankenphp run --config /etc/frankenphp/Caddyfile
