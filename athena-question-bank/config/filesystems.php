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
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Supabase Storage — ATHENA Question Bank (S3 Compatible)
        |--------------------------------------------------------------------------
        */
        'question_bank_storage' => [
            'driver' => 's3',
            'key' => env('QB_STORAGE_ACCESS_KEY'),
            'secret' => env('QB_STORAGE_SECRET_KEY'),
            'region' => env('QB_STORAGE_REGION', 'us-east-1'),
            'bucket' => env('QB_STORAGE_DOCUMENTS_BUCKET', 'icfes-documents'),
            'endpoint' => env('QB_STORAGE_ENDPOINT'),
            'use_path_style_endpoint' => env('QB_STORAGE_USE_PATH_STYLE_ENDPOINT', true),
            'visibility' => 'private',
            'throw' => true,
        ],

        'question_bank_documents' => [
            'driver' => 's3',
            'key' => env('QB_STORAGE_ACCESS_KEY'),
            'secret' => env('QB_STORAGE_SECRET_KEY'),
            'region' => env('QB_STORAGE_REGION', 'us-east-1'),
            'bucket' => env('QB_STORAGE_DOCUMENTS_BUCKET', 'icfes-documents'),
            'endpoint' => env('QB_STORAGE_ENDPOINT'),
            'use_path_style_endpoint' => env('QB_STORAGE_USE_PATH_STYLE_ENDPOINT', true),
            'visibility' => 'private',
            'throw' => true,
        ],

        'question_bank_media' => [
            'driver' => 's3',
            'key' => env('QB_STORAGE_ACCESS_KEY'),
            'secret' => env('QB_STORAGE_SECRET_KEY'),
            'region' => env('QB_STORAGE_REGION', 'us-east-1'),
            'bucket' => env('QB_STORAGE_MEDIA_BUCKET', 'icfes-media'),
            'endpoint' => env('QB_STORAGE_ENDPOINT'),
            'use_path_style_endpoint' => env('QB_STORAGE_USE_PATH_STYLE_ENDPOINT', true),
            'visibility' => 'private',
            'throw' => true,
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
