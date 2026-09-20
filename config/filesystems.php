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
    | Locally this stays 'public'. On any host with an ephemeral filesystem —
    | Vercel and every other serverless runtime — it must point at object
    | storage ('s3'), or every upload disappears with the container that
    | received it. Changing this one value moves all seven call sites.
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

        's3' => [
            'driver' => 's3',
            // R2_* y no AWS_*, con los segundos como respaldo para desarrollo.
            // Vercel inyecta sus propios AWS_ACCESS_KEY_ID y AWS_SECRET_ACCESS_KEY
            // en el contenedor y pisan los del proyecto: llegan vacios, el SDK se
            // queda sin credenciales y acaba preguntando al servicio de metadatos
            // de EC2, que en Vercel no existe. El sintoma es un timeout de 1s al
            // guardar cualquier imagen del panel, sin mencion alguna a las
            // credenciales. Su documentacion lo reconoce: los runtimes de
            // contenedor "must use alternative environment variable names".
            //
            // Solo estos dos nombres estan afectados. AWS_BUCKET, AWS_ENDPOINT,
            // AWS_URL y AWS_DEFAULT_REGION no los toca la plataforma y llegan
            // intactos, asi que se dejan como estan.
            'key' => env('R2_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID')),
            'secret' => env('R2_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY')),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            // true, unlike every other disk here. This is the uploads disk in
            // production, and a rejected write returns false rather than
            // raising: Filament would save the record with a path to an object
            // that was never stored, and the broken crest would be the only
            // symptom, with nothing in the logs. Failing loudly turns that into
            // a visible error at the moment the admin uploads the file.
            'throw' => true,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
