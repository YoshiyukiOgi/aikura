<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Retail System Boundary
    |--------------------------------------------------------------------------
    |
    | The retail module runs inside this Laravel app for now, but these settings
    | keep routing and persistence isolated enough to move it to a subdomain,
    | another domain, or another database later.
    |
    */

    'domain' => env('RETAIL_DOMAIN'),

    'route_prefix' => env('RETAIL_ROUTE_PREFIX', 'retail'),

    'database' => [
        'connection' => env('RETAIL_DB_CONNECTION', 'retail'),
    ],

    'companies' => [
        'maru' => [
            'name' => '○社',
            'description' => '銀座店・新宿店・横浜店を運営',
        ],
        'batsu' => [
            'name' => '×社',
            'description' => '法人向けギフト販売を運営',
        ],
        'sankaku' => [
            'name' => '▲社',
            'description' => '飲食店向け小売と外部仕入を運営',
        ],
    ],

    'procurement_sources' => [
        'brewery' => '蔵商品',
        'external' => '外部商品',
    ],
];
