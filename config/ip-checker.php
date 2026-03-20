<?php

use App\Services\Providers\AbuseIpDbProvider;
use App\Services\Providers\EmailRepProvider;
use App\Services\Providers\IpApiProvider;
use App\Services\Providers\IpInfoProvider;
use App\Services\Providers\MockProvider;
use App\Services\Providers\VirusTotalProvider;

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
        'abuseipdb' => [
            'class' => AbuseIpDbProvider::class,
            'priority' => 10,
            'api_key' => env('ABUSEIPDB_API_KEY'),
            'base_url' => 'https://api.abuseipdb.com/api/v2',
            'timeout' => 10,
        ],
        'virustotal' => [
            'class' => VirusTotalProvider::class,
            'priority' => 20,
            'api_key' => env('VIRUSTOTAL_API_KEY'),
            'base_url' => 'https://www.virustotal.com/api/v3',
            'timeout' => 15,
        ],
        'ip_api' => [
            'class' => IpApiProvider::class,
            'priority' => 30,
            'base_url' => 'http://ip-api.com/json',
            'timeout' => 5,
        ],
        'ipinfo' => [
            'class' => IpInfoProvider::class,
            'priority' => 40,
            'api_key' => env('IPINFO_TOKEN'),
            'base_url' => 'https://ipinfo.io',
            'timeout' => 5,
        ],
        'emailrep' => [
            'class' => EmailRepProvider::class,
            'priority' => 10,
            'api_key' => env('EMAILREP_API_KEY'),
            'base_url' => 'https://emailrep.io',
            'timeout' => 10,
        ],
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
