<?php

/*
 * Qué proxies pueden decirnos quién es el visitante (X-Forwarded-*). Lo lee el
 * middleware TrustProxies de Laravel por su cuenta.
 *
 * Vacío a propósito: en Hostinger el servidor web recibe la conexión del propio
 * visitante, y confiar en esas cabeceras dejaría que cada cual eligiera su IP
 * (ver bootstrap/app.php). Si algún día se pone un CDN delante, sus rangos van
 * en TRUSTED_PROXIES, separados por comas, y no '*': '*' sólo es seguro cuando
 * NADIE puede llegar al servidor sin pasar por ese proxy.
 */
return [
    'proxies' => env('TRUSTED_PROXIES'),
];
