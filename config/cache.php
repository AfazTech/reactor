<?php

return [
    'default' => env('CACHE_DRIVER', 'file'),

    'stores' => [
        'redis' => [
            'driver' => 'redis',
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', null),
            'database' => env('REDIS_DATABASE', 0),
            'prefix' => env('CACHE_PREFIX', 'reactor:'),
        ],

        'memcached' => [
            'driver' => 'memcached',
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                ],
            ],
            'prefix' => env('CACHE_PREFIX', 'reactor:'),
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('cache'),
        ],

        'array' => [
            'driver' => 'array',
        ],
    ],
];
