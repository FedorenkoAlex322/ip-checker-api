<?php

return [
    'default' => [
        'failure_threshold' => (int) env('CIRCUIT_BREAKER_THRESHOLD', 5),
        'recovery_timeout' => (int) env('CIRCUIT_BREAKER_RECOVERY_TIME', 30),
        'success_threshold' => (int) env('CIRCUIT_BREAKER_SUCCESS_THRESHOLD', 3),
    ],

    'retry' => [
        'max_retries' => (int) env('RETRY_MAX_ATTEMPTS', 3),
        'base_delay_ms' => (int) env('RETRY_BASE_DELAY_MS', 200),
        'max_delay_ms' => (int) env('RETRY_MAX_DELAY_MS', 5000),
        'multiplier' => 2.0,
        'jitter_enabled' => true,
    ],

    'providers' => [
        'abuseipdb' => [
            'failure_threshold' => 3,
            'recovery_timeout' => 60,
        ],
        'virustotal' => [
            'failure_threshold' => 3,
            'recovery_timeout' => 60,
        ],
        'emailrep' => [
            'failure_threshold' => 3,
            'recovery_timeout' => 60,
        ],
    ],

    'redis_prefix' => 'circuit',
    'sync_to_db_interval' => 60,
];
