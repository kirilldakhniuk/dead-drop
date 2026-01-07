<?php

/*
|--------------------------------------------------------------------------
| Dead Drop Configuration
|--------------------------------------------------------------------------
|
| This file controls how Dead Drop exports and imports your database tables.
| You MUST configure the 'tables' array below to define which tables to export
| and which columns to include. See documentation for all available options.
|
*/

return [
    /*
    |--------------------------------------------------------------------------
    | Default Output Path
    |--------------------------------------------------------------------------
    |
    | The default directory where exported SQL files will be saved locally.
    |
    */
    'output_path' => storage_path('app/dead-drop'),

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configure where to store exported files. Options:
    | - 'local': Only save to local filesystem
    | - 's3': Upload to Amazon S3
    | - 'spaces': Upload to DigitalOcean Spaces
    | - Any Laravel filesystem disk name
    |
    */
    'storage' => [
        'disk' => env('DEAD_DROP_STORAGE_DISK', 'local'),

        'path' => env('DEAD_DROP_STORAGE_PATH', 'dead-drop'),

        'delete_local_after_upload' => env('DEAD_DROP_DELETE_LOCAL', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which tables to export and which columns to include.
    | Set a table to `false` to disable export for that table.
    |
    | Available options per table:
    | - columns: Array of columns to export (or '*' for all)
    | - where: Array of where conditions
    | - order_by: Order by clause (e.g., 'created_at DESC')
    | - limit: Maximum number of records to export
    | - censor: Array of columns to replace with fake data
    |   - Simple format: ['email', 'phone'] - auto-detects faker method
    |   - Advanced format: ['email' => 'safeEmail', 'name' => 'name', 'ip' => 'ipv4']
    | - defaults: Default values for required fields not included in 'columns'
    |   - Example: ['password' => 'password', 'status' => 'active']
    |   - Prevents NOT NULL constraint violations on import
    |   - Password fields are automatically hashed with bcrypt for security
    |   - Supported password fields: password, password_hash, passwd, user_password
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

        // More examples:
        // 'posts' => [
        //     'columns' => '*',
        //     'where' => [['status', '=', 'published']],
        //     'order_by' => 'created_at DESC',
        // ],

        // Disable export for specific table:
        // 'logs' => false,
    ],
];
