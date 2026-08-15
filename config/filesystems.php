<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    */

    'disks' => [

        /*
        |--------------------------------------------------------------------------
        | Private Application Storage
        |--------------------------------------------------------------------------
        |
        | General private files that should not be publicly accessible.
        |
        */

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Private Book Files
        |--------------------------------------------------------------------------
        |
        | Used for eBooks, PDFs, and other private book files.
        |
        */

        'books' => [
            'driver' => 'local',
            'root' => storage_path('app/private/books'),
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Private Audiobook Files
        |--------------------------------------------------------------------------
        |
        | Used for audiobook audio files.
        |
        | These files must remain private because audiobook access
        | is controlled through AudiobookEntitlement.
        |
        */

        'audiobooks' => [
            'driver' => 'local',
            'root' => storage_path('app/private/audiobooks'),
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Public Files
        |--------------------------------------------------------------------------
        |
        | Used for files that are safe to expose publicly, such as:
        |
        | - Book covers
        | - Audiobook artwork
        | - Author images
        | - Other catalogue images
        |
        */

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(
                env('APP_URL', 'http://localhost'),
                '/'
            ) . '/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Amazon S3
        |--------------------------------------------------------------------------
        |
        | Can be used later for scalable production file storage.
        |
        */

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env(
                'AWS_USE_PATH_STYLE_ENDPOINT',
                false
            ),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];