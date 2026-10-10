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
    | Supported drivers: "local", "ftp", "sftp"
    |
    | No hay disco de objetos: se retiro con el bucket de R2 al mudarse a
    | Hostinger, y con el el paquete league/flysystem-aws-s3-v3. Si algun dia
    | hiciera falta, vuelve a instalarse y se declara aqui.
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

    ],

];
