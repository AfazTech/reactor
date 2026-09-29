<?php

return [
    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
     * Migration settings.
     *
     * The namespace where application migrations live. This must match
     * the namespace declared at the top of each migration file inside
     * database/migrations. Override via MIGRATIONS_NAMESPACE in .env
     * if you prefer a different namespace (e.g. "Domain\\Migrations").
     */
    'migrations' => [
        'namespace' => env('MIGRATIONS_NAMESPACE', 'App\\Migrations'),
    ],

    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', database_path('test.sqlite')),
            'prefix' => '',
        ],
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => 'InnoDB',
        ],
    ],
];
