<?php

declare(strict_types=1);
use App\Services\Providers\MockProvider;

return [
    /*
    |--------------------------------------------------------------------------
    | Lookup Providers
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'mock' => [
            'enabled' => env('PROVIDER_MOCK_ENABLED', true),
            'class' => MockProvider::class,
            'priority' => 100,
            'supports' => ['ip', 'domain', 'email'],
            'delay_ms' => env('PROVIDER_MOCK_DELAY_MS', 50),
        ],
        // Real providers will be added here
        // 'abuseipdb' => [
        //     'enabled' => env('PROVIDER_ABUSEIPDB_ENABLED', false),
        //     'class' => \App\Services\Providers\AbuseIpDbProvider::class,
        //     'priority' => 10,
        //     'supports' => ['ip'],
        //     'api_key' => env('PROVIDER_ABUSEIPDB_KEY'),
        //     'base_url' => 'https://api.abuseipdb.com/api/v2',
        //     'timeout' => 5,
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache TTL (seconds)
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'ttl' => [
            'ip' => (int) env('CACHE_TTL_IP_LOOKUP', 3600),
            'domain' => (int) env('CACHE_TTL_DOMAIN_LOOKUP', 3600),
            'email' => (int) env('CACHE_TTL_EMAIL_LOOKUP', 3600),
        ],
        'stale_ttl' => (int) env('CACHE_STALE_TTL', 86400),
        'prefix' => 'lookup',
    ],

    /*
    |--------------------------------------------------------------------------
    | Lookup Log Retention
    |--------------------------------------------------------------------------
    */
    'log_retention_days' => (int) env('LOOKUP_LOG_RETENTION_DAYS', 90),
];
