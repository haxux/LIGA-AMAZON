<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Uploads Disk
    |--------------------------------------------------------------------------
    |
    | Where admin-uploaded images (team crests, news covers) are written and
    | served from. Deliberately separate from 'default': that one is 'local',
    | which is private storage, while these files must be publicly readable.
    |
    | Desde la mudanza a Hostinger esto es 'public', el disco del propio
    | servidor: hay disco persistente, así que las subidas se quedan donde se
    | escriben. Vivieron un tiempo en un bucket de objetos porque el disco de
    | Vercel era efímero y cada despliegue se llevaba las imágenes por delante;
    | ese motivo ya no existe.
    |
    | Cambiar este único valor mueve los siete sitios que suben o sirven.
    |
    */

    'uploads' => env('UPLOADS_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Queda el disco s3 estándar de Laravel, sin usar: las subidas van al
        // disco del servidor. Está aquí por si algún día hace falta
        // almacenamiento de objetos, no porque hoy se use.
        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            // true, a diferencia de los demás: una escritura rechazada devuelve
            // false en vez de levantar, y el panel guardaría la ficha con la
            // ruta de un fichero que nunca se escribió.
            'throw' => true,
            'report' => false,
        ],

    ],

];
