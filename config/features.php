<?php

return [
    'throttle' => [
        // Memory is per process. Use Redis for a shared limit across workers.
        'driver' => env('THROTTLE_DRIVER', 'memory'),
        'redis_uri' => env('THROTTLE_REDIS_URI', 'redis://127.0.0.1:6379'),
    ],
];
