<?php

declare(strict_types=1);

namespace App\Services\Lookup;

use App\Contracts\CircuitBreakerInterface;
use App\Contracts\LookupCacheInterface;
use App\Contracts\LookupResultRepositoryInterface;
use App\Contracts\ProviderRegistryInterface;
use App\Contracts\RetryableInterface;
use App\DTOs\LookupResult;
use App\DTOs\RetryConfig;
use App\Enums\LookupType;
use App\Exceptions\ProviderUnavailableException;
use App\Models\LookupResult as LookupResultModel;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

final class LookupService
{
    public function __construct(
        private readonly LookupCacheInterface $cache,
        private readonly ProviderRegistryInterface $providerRegistry,
        private readonly CircuitBreakerInterface $circuitBreaker,
        private readonly RetryableInterface $retry,
        private readonly LookupResultRepositoryInterface $resultRepository,
    ) {}

    /**
     * Perform a lookup for the given target (IP, domain, or email).
     *
     * Flow:
     * 1. Check cache -> return if fresh hit
     * 2. Try each available provider with retry logic
     * 3. On success: record CB success, store result, cache, return
     * 4. On failure: record CB failure, try next provider
     * 5. If all providers failed: fallback to stale cache
     * 6. If no stale cache: throw ProviderUnavailableException
     *
     * @throws ProviderUnavailableException
     */
    public function lookup(string $target, LookupType $type, int $apiKeyId): LookupResult
    {
        // 1. Check cache — return immediately on fresh hit
        $cached = $this->cache->get($target, $type);

        if ($cached !== null && ! $cached->isStale) {
            Log::debug('LookupService: cache hit', [
                'target' => $target,
                'type' => $type->value,
                'provider' => $cached->result->provider,
            ]);

            return new LookupResult(
                uuid: $cached->result->uuid,
                target: $cached->result->target,
                type: $cached->result->type,
                provider: $cached->result->provider,
                resultData: $cached->result->resultData,
                lookupTimeMs: 0.0,
                cached: true,
            );
        }

        // 2. Get available providers sorted by priority
        $providers = $this->providerRegistry->getAvailableProviders($type);

        if ($providers === []) {
            Log::warning('LookupService: no available providers', [
                'target' => $target,
                'type' => $type->value,
            ]);

            return $this->fallbackToStaleCache($target, $type);
        }

        // 3. Try each provider with retry
        $lastException = null;

        foreach ($providers as $provider) {
            $providerName = $provider->getName();

            try {
                $retryConfig = $this->buildRetryConfig($providerName);

                /** @var LookupResult $result */
                $result = $this->retry->execute(
                    fn (): LookupResult => $provider->lookup($target, $type),
                    $retryConfig,
                );

                // 4. Success path
                $this->circuitBreaker->recordSuccess($providerName);
                $this->cache->put($target, $type, $result);
                $this->resultRepository->store($result, $apiKeyId);

                Log::info('LookupService: lookup succeeded', [
                    'target' => $target,
                    'type' => $type->value,
                    'provider' => $providerName,
                    'lookup_time_ms' => $result->lookupTimeMs,
                ]);

                return $result;
            } catch (\Throwable $e) {
                // 5. Failure path — record and move to next provider
                $lastException = $e;
                $this->circuitBreaker->recordFailure($providerName);

                Log::warning('LookupService: provider failed, trying next', [
                    'target' => $target,
                    'type' => $type->value,
                    'provider' => $providerName,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 6. All providers exhausted — try stale cache as last resort
        Log::error('LookupService: all providers failed', [
            'target' => $target,
            'type' => $type->value,
            'last_error' => $lastException->getMessage(),
        ]);

        return $this->fallbackToStaleCache($target, $type, $lastException);
    }

    /**
     * Find a stored lookup result by its UUID.
     */
    public function findByUuid(string $uuid): ?LookupResultModel
    {
        return $this->resultRepository->findByUuid($uuid);
    }

    /**
     * Get paginated lookup history for an API key.
     */
    public function getHistory(int $apiKeyId, int $perPage = 20): LengthAwarePaginator
    {
        return $this->resultRepository->getHistory($apiKeyId, $perPage);
    }

    /**
     * Attempt to return stale cached data as a degraded response.
     *
     * @throws ProviderUnavailableException when no stale cache is available
     */
    private function fallbackToStaleCache(
        string $target,
        LookupType $type,
        ?\Throwable $previousException = null,
    ): LookupResult {
        $stale = $this->cache->getStale($target, $type);

        if ($stale !== null) {
            Log::info('LookupService: returning stale cache as fallback', [
                'target' => $target,
                'type' => $type->value,
                'provider' => $stale->provider,
            ]);

            return new LookupResult(
                uuid: $stale->uuid,
                target: $stale->target,
                type: $stale->type,
                provider: $stale->provider,
                resultData: $stale->resultData,
                lookupTimeMs: 0.0,
                cached: true,
            );
        }

        Log::error('LookupService: no stale cache available, all options exhausted', [
            'target' => $target,
            'type' => $type->value,
            'previous_error' => $previousException?->getMessage(),
        ]);

        throw new ProviderUnavailableException(
            providerName: 'all',
            message: 'All providers are unavailable and no cached data exists.',
        );
    }

    /**
     * Build retry config with per-provider overrides falling back to defaults.
     */
    private function buildRetryConfig(string $providerName): RetryConfig
    {
        /** @var array<string, mixed>|null $providerRetry */
        $providerRetry = config("circuit-breaker.providers.{$providerName}.retry");

        /** @var array<string, mixed> $defaultRetry */
        $defaultRetry = config('circuit-breaker.retry');

        $config = $providerRetry ?? $defaultRetry;

        return RetryConfig::fromArray($config);
    }
}
