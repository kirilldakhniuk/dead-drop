<?php

/*
|--------------------------------------------------------------------------
| Dead Drop Configuration
|--------------------------------------------------------------------------
|
| Configure database export and import settings. Define which tables to
| export, columns to include, and storage destinations.
|
*/

return [
    /*
    |--------------------------------------------------------------------------
    | Output Path
    |--------------------------------------------------------------------------
    |
    | Local directory for exported SQL files.
    |
    */
    'output_path' => storage_path('app/dead-drop'),

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Queue connection for async jobs. Uses Laravel's default when null.
    |
    */
    'queue' => [
        'connection' => env('DEAD_DROP_QUEUE_CONNECTION'),
        'queue_name' => env('DEAD_DROP_QUEUE_NAME', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Export destination: 'local', 's3', 'spaces', or any Laravel disk.
    |
    */
    'storage' => [
        'disk' => env('DEAD_DROP_STORAGE_DISK', 'local'),

        'path' => env('DEAD_DROP_STORAGE_PATH', 'dead-drop'),

        'delete_local_after_upload' => env('DEAD_DROP_DELETE_LOCAL', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    |
    | Define tables and columns to export. Set to `false` to disable.
    |
    | Options:
    | - columns: Column list or '*' for all
    | - where: Filter conditions
    | - order_by: Sort clause (e.g., 'created_at DESC')
    | - limit: Maximum records
    | - censor: Replace with fake data (['email'] or ['email' => 'safeEmail'])
    | - defaults: Values for omitted NOT NULL fields (passwords auto-hashed)
    |
    */
    'tables' => [
        'users' => [
            'columns' => ['id', 'name', 'email', 'created_at', 'updated_at'],
            'censor' => ['email', 'name'],
            'where' => [
                ['created_at', '<', now()->subDays(30)],
            ],
            'limit' => 1000,
        ],

        // 'posts' => [
        //     'columns' => '*',
        //     'where' => [['status', '=', 'published']],
        //     'order_by' => 'created_at DESC',
        // ],

        // 'logs' => false,
    ],
];
