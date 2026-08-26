<?php

use Illuminate\Support\Str;

return [
    'default' => env('DB_CONNECTION', 'pgsql'),
    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'aikura'),
            'username' => env('DB_USERNAME', 'aikura'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],
        'retail' => [
            'driver' => 'pgsql',
            'url' => env('RETAIL_DB_URL', env('DB_URL')),
            'host' => env('RETAIL_DB_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('RETAIL_DB_PORT', env('DB_PORT', '5432')),
            'database' => env('RETAIL_DB_DATABASE', env('DB_DATABASE', 'aikura')),
            'username' => env('RETAIL_DB_USERNAME', env('DB_USERNAME', 'aikura')),
            'password' => env('RETAIL_DB_PASSWORD', env('DB_PASSWORD', '')),
            'charset' => env('RETAIL_DB_CHARSET', env('DB_CHARSET', 'utf8')),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => env('RETAIL_DB_SCHEMA', 'public'),
            'sslmode' => env('RETAIL_DB_SSLMODE', 'prefer'),
        ],
    ],
    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],
    'redis' => [
        'client' => env('REDIS_CLIENT', 'predis'),
        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'aikura'), '_').'_database_'),
        ],
        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],
        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],
    ],
];

