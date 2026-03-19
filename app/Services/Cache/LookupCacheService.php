<?php

declare(strict_types=1);

namespace App\Services\Cache;

use App\Contracts\LookupCacheInterface;
use App\DTOs\CachedLookupResult;
use App\DTOs\LookupResult;
use App\Enums\LookupType;
use Illuminate\Support\Facades\Redis;

final class LookupCacheService implements LookupCacheInterface
{
    private readonly string $prefix;

    public function __construct()
    {
        $this->prefix = config('ip-checker.cache.prefix', 'lookup');
    }

    public function get(string $target, LookupType $type): ?CachedLookupResult
    {
        $key = $this->generateKey($target, $type);
        $data = Redis::get($key);

        if ($data === null || $data === false) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $data, true, 512, JSON_THROW_ON_ERROR);
        $ttl = (int) Redis::ttl($key);

        $cachedAt = $decoded['_cached_at'] ?? now()->toIso8601String();
        unset($decoded['_cached_at']);

        return new CachedLookupResult(
            result: LookupResult::fromArray($decoded),
            cachedAt: $cachedAt,
            ttl: max(0, $ttl),
            isStale: false,
        );
    }

    public function put(string $target, LookupType $type, LookupResult $result): void
    {
        $key = $this->generateKey($target, $type);
        $staleKey = $this->generateStaleKey($target, $type);

        $data = $result->toArray();
        $data['_cached_at'] = now()->toIso8601String();
        $encoded = json_encode($data, JSON_THROW_ON_ERROR);

        $ttl = $this->getTtl($type);
        $staleTtl = (int) config('ip-checker.cache.stale_ttl', 86400);

        Redis::connection()->command('setex', [$key, $ttl, $encoded]);
        Redis::connection()->command('setex', [$staleKey, $staleTtl, $encoded]);
    }

    public function forget(string $target, LookupType $type): void
    {
        $key = $this->generateKey($target, $type);
        $staleKey = $this->generateStaleKey($target, $type);

        Redis::del([$key, $staleKey]);
    }

    public function getStale(string $target, LookupType $type): ?LookupResult
    {
        $staleKey = $this->generateStaleKey($target, $type);
        $data = Redis::get($staleKey);

        if ($data === null || $data === false) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $data, true, 512, JSON_THROW_ON_ERROR);
        unset($decoded['_cached_at']);

        return LookupResult::fromArray($decoded);
    }

    public function generateKey(string $target, LookupType $type): string
    {
        $normalized = $this->normalizeTarget($target, $type);

        return "{$this->prefix}:{$type->value}:{$normalized}";
    }

    private function generateStaleKey(string $target, LookupType $type): string
    {
        $normalized = $this->normalizeTarget($target, $type);

        return "{$this->prefix}:stale:{$type->value}:{$normalized}";
    }

    private function getTtl(LookupType $type): int
    {
        return (int) config("ip-checker.cache.ttl.{$type->value}", 3600);
    }

    private function normalizeTarget(string $target, LookupType $type): string
    {
        $target = strtolower(trim($target));

        if ($type === LookupType::Domain) {
            $target = rtrim($target, '.');
        }

        return $target;
    }
}
