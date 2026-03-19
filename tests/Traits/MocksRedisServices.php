<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Contracts\CircuitBreakerInterface;
use App\Contracts\LookupCacheInterface;
use App\Contracts\QuotaServiceInterface;
use App\Contracts\RateLimiterInterface;
use App\DTOs\QuotaStatus;
use App\DTOs\RateLimitResult;
use App\Enums\ApiKeyTier;
use App\Models\ApiKey;
use Mockery;
use Mockery\MockInterface;

/**
 * Provides helper methods to mock Redis-dependent services in feature tests.
 *
 * All mocks return "happy path" defaults. Individual tests can override
 * behaviour by calling the mock factory methods with custom parameters
 * or by re-binding the interface after setUp().
 */
trait MocksRedisServices
{
    protected string $plaintextKey;

    protected ApiKey $apiKey;

    // ------------------------------------------------------------------
    // Bootstrap
    // ------------------------------------------------------------------

    /**
     * Call this from setUp() to create a test API key and bind all mocks.
     */
    protected function setUpApiKeyAndMocks(ApiKeyTier $tier = ApiKeyTier::Free): void
    {
        $this->plaintextKey = 'test-key-'.bin2hex(random_bytes(16));

        $factoryMethod = match ($tier) {
            ApiKeyTier::Free => 'free',
            ApiKeyTier::Pro => 'pro',
            ApiKeyTier::Enterprise => 'enterprise',
        };

        $this->apiKey = ApiKey::factory()->{$factoryMethod}()->create([
            'key_hash' => hash('sha256', $this->plaintextKey),
        ]);

        $this->mockRateLimiter();
        $this->mockQuotaService();
        $this->mockLookupCache();
        $this->mockCircuitBreaker();
    }

    // ------------------------------------------------------------------
    // Rate Limiter
    // ------------------------------------------------------------------

    protected function mockRateLimiter(bool $allowed = true, int $retryAfter = 0): MockInterface
    {
        $mock = Mockery::mock(RateLimiterInterface::class);
        $mock->shouldReceive('attempt')->andReturn(new RateLimitResult(
            allowed: $allowed,
            limit: 60,
            remaining: $allowed ? 59 : 0,
            retryAfter: $retryAfter,
            resetAt: time() + 60,
        ));
        $mock->shouldReceive('remaining')->andReturn($allowed ? 59 : 0);
        $mock->shouldReceive('clear')->andReturnNull();
        $this->app->instance(RateLimiterInterface::class, $mock);

        return $mock;
    }

    // ------------------------------------------------------------------
    // Quota Service
    // ------------------------------------------------------------------

    protected function mockQuotaService(bool $allowed = true): MockInterface
    {
        $mock = Mockery::mock(QuotaServiceInterface::class);
        $mock->shouldReceive('check')->andReturn(new QuotaStatus(
            allowed: $allowed,
            tier: ApiKeyTier::Free,
            dailyUsed: 0,
            dailyLimit: 1000,
            monthlyUsed: 0,
            monthlyLimit: 10000,
        ));
        $mock->shouldReceive('increment')->andReturnNull();
        $mock->shouldReceive('getUsage')->andReturnNull();
        $this->app->instance(QuotaServiceInterface::class, $mock);

        return $mock;
    }

    // ------------------------------------------------------------------
    // Lookup Cache
    // ------------------------------------------------------------------

    protected function mockLookupCache(): MockInterface
    {
        $mock = Mockery::mock(LookupCacheInterface::class);
        $mock->shouldReceive('get')->andReturnNull();
        $mock->shouldReceive('getStale')->andReturnNull();
        $mock->shouldReceive('put')->andReturnNull();
        $mock->shouldReceive('forget')->andReturnNull();
        $mock->shouldReceive('generateKey')->andReturn('mock-cache-key');
        $this->app->instance(LookupCacheInterface::class, $mock);

        return $mock;
    }

    // ------------------------------------------------------------------
    // Circuit Breaker
    // ------------------------------------------------------------------

    protected function mockCircuitBreaker(): MockInterface
    {
        $mock = Mockery::mock(CircuitBreakerInterface::class);
        $mock->shouldReceive('isAvailable')->andReturn(true);
        $mock->shouldReceive('recordSuccess')->andReturnNull();
        $mock->shouldReceive('recordFailure')->andReturnNull();
        $mock->shouldReceive('getAllStates')->andReturn([]);
        $mock->shouldReceive('getState')->andReturnNull();
        $mock->shouldReceive('reset')->andReturnNull();
        $this->app->instance(CircuitBreakerInterface::class, $mock);

        return $mock;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Return headers array with the test API key.
     *
     * @return array<string, string>
     */
    protected function apiKeyHeaders(): array
    {
        return ['X-API-Key' => $this->plaintextKey];
    }
}
