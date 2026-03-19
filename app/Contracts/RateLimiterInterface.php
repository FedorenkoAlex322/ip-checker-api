<?php

namespace App\Contracts;

use App\DTOs\RateLimitResult;

interface RateLimiterInterface
{
    public function attempt(string $key, int $maxAttempts, int $decaySeconds): RateLimitResult;

    public function remaining(string $key, int $maxAttempts, int $decaySeconds): int;

    public function clear(string $key): void;
}
