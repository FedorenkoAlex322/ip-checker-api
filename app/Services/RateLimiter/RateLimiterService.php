<?php

namespace App\Services\RateLimiter;

use App\Contracts\RateLimiterInterface;
use App\DTOs\RateLimitResult;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

final class RateLimiterService implements RateLimiterInterface
{
    private const string LUA_ATTEMPT_SCRIPT = <<<'LUA'
        local key = KEYS[1]
        local now = tonumber(ARGV[1])
        local window = tonumber(ARGV[2])
        local max_attempts = tonumber(ARGV[3])
        local member = ARGV[4]

        -- Remove expired entries outside the sliding window
        redis.call('ZREMRANGEBYSCORE', key, '-inf', now - window)

        -- Count current entries in the window
        local count = redis.call('ZCARD', key)

        if count < max_attempts then
            -- Allow: add new entry and refresh TTL
            redis.call('ZADD', key, now, member)
            redis.call('EXPIRE', key, window)
            return {1, max_attempts - count - 1, 0}
        else
            -- Deny: calculate retry_after from oldest entry
            local oldest = redis.call('ZRANGE', key, 0, 0, 'WITHSCORES')
            local retry_after = 0
            if #oldest > 0 then
                retry_after = math.ceil(oldest[2] + window - now)
            end
            return {0, 0, retry_after}
        end
        LUA;

    private readonly string $prefix;

    public function __construct()
    {
        /** @var string $prefix */
        $prefix = config('rate-limiting.redis_prefix', 'ratelimit');
        $this->prefix = $prefix;
    }

    public function attempt(string $key, int $maxAttempts, int $decaySeconds): RateLimitResult
    {
        $redisKey = $this->getRedisKey($key);
        $now = microtime(true);
        $member = Str::uuid()->toString();

        /** @var array{0: int, 1: int, 2: int} $result */
        $result = Redis::connection()->command('eval', [
            self::LUA_ATTEMPT_SCRIPT,
            1,
            $redisKey,
            (string) $now,
            (string) $decaySeconds,
            (string) $maxAttempts,
            $member,
        ]);

        $allowed = (bool) $result[0];
        $remaining = (int) $result[1];
        $retryAfter = (int) $result[2];
        $resetAt = (int) ceil($now + $decaySeconds);

        return new RateLimitResult(
            allowed: $allowed,
            limit: $maxAttempts,
            remaining: $remaining,
            retryAfter: $retryAfter,
            resetAt: $resetAt,
        );
    }

    public function remaining(string $key, int $maxAttempts, int $decaySeconds): int
    {
        $redisKey = $this->getRedisKey($key);
        $now = microtime(true);

        Redis::zRemRangeByScore($redisKey, '-inf', (string) ($now - $decaySeconds));

        /** @var int $count */
        $count = Redis::zCard($redisKey);

        return max(0, $maxAttempts - $count);
    }

    public function clear(string $key): void
    {
        Redis::del($this->getRedisKey($key));
    }

    private function getRedisKey(string $key): string
    {
        return "{$this->prefix}:{$key}";
    }
}
