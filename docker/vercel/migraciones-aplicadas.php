<?php

/*
 * Cuántas migraciones tiene aplicadas la base, dicho con PDO y sin arrancar
 * Laravel.
 *
 * El entrypoint lo usa para no pagar el migrador entero en cada arranque en
 * frío. Medido contra la base real: `migrate:status` más `migrate --force
 * --isolated` cuestan unos 4,2 s —dos arranques completos del framework y
 * varias idas y vueltas a la base—, mientras que conectar y contar cuesta 0,6 s.
 * Como el contenedor escala a cero y arranca muchas veces al día, esos 3,6 s se
 * pagaban una y otra vez para descubrir, casi siempre, que no había nada que
 * migrar.
 *
 * Escribe un número en la salida estándar, y `-1` si no se puede saber —base
 * caída, o recién creada y sin tabla `migrations`—. El entrypoint trata ese -1
 * como «hay que migrar», que es la respuesta prudente: ante la duda, migra.
 */
$socket = getenv('DB_SOCKET');
$dsn = $socket
    ? sprintf('mysql:unix_socket=%s;dbname=%s', $socket, getenv('DB_DATABASE'))
    : sprintf('mysql:host=%s;port=%s;dbname=%s', getenv('DB_HOST'), getenv('DB_PORT') ?: '3306', getenv('DB_DATABASE'));

try {
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 10,
    ];

    if ($ca = getenv('MYSQL_ATTR_SSL_CA')) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
    }

    $pdo = new PDO($dsn, getenv('DB_USERNAME'), getenv('DB_PASSWORD'), $options);

    echo (int) $pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn();
} catch (Throwable) {
    echo -1;
}
