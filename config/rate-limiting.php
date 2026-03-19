<?php

declare(strict_types=1);

return [
    'tiers' => [
        'free' => [
            'requests_per_minute' => (int) env('RATE_LIMIT_FREE', 60),
            'daily_limit' => (int) env('QUOTA_DAILY_FREE', 1000),
            'monthly_limit' => (int) env('QUOTA_MONTHLY_FREE', 10000),
        ],
        'pro' => [
            'requests_per_minute' => (int) env('RATE_LIMIT_PRO', 300),
            'daily_limit' => (int) env('QUOTA_DAILY_PRO', 50000),
            'monthly_limit' => (int) env('QUOTA_MONTHLY_PRO', 1000000),
        ],
        'enterprise' => [
            'requests_per_minute' => (int) env('RATE_LIMIT_ENTERPRISE', 1000),
            'daily_limit' => (int) env('QUOTA_DAILY_ENTERPRISE', 500000),
            'monthly_limit' => (int) env('QUOTA_MONTHLY_ENTERPRISE', 10000000),
        ],
    ],

    'sliding_window_size' => 60,
    'redis_prefix' => 'ratelimit',
];
