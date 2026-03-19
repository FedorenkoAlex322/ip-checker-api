<?php

namespace Tests\Unit\Services;

use App\Contracts\CircuitBreakerInterface;
use App\Contracts\LookupCacheInterface;
use App\Contracts\LookupProviderInterface;
use App\Contracts\LookupResultRepositoryInterface;
use App\Contracts\ProviderRegistryInterface;
use App\Contracts\RetryableInterface;
use App\DTOs\CachedLookupResult;
use App\DTOs\LookupResult;
use App\Enums\LookupType;
use App\Events\LookupCompleted;
use App\Events\LookupFailed;
use App\Exceptions\ProviderUnavailableException;
use App\Services\Lookup\LookupService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LookupServiceTest extends TestCase
{
    private LookupCacheInterface&MockInterface $cache;

    private ProviderRegistryInterface&MockInterface $providerRegistry;

    private CircuitBreakerInterface&MockInterface $circuitBreaker;

    private RetryableInterface&MockInterface $retry;

    private LookupResultRepositoryInterface&MockInterface $resultRepository;

    private LookupService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = Mockery::mock(LookupCacheInterface::class);
        $this->providerRegistry = Mockery::mock(ProviderRegistryInterface::class);
        $this->circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $this->retry = Mockery::mock(RetryableInterface::class);
        $this->resultRepository = Mockery::mock(LookupResultRepositoryInterface::class);

        $this->service = new LookupService(
            cache: $this->cache,
            providerRegistry: $this->providerRegistry,
            circuitBreaker: $this->circuitBreaker,
            retry: $this->retry,
            resultRepository: $this->resultRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function test_returns_cached_result_on_cache_hit(): void
    {
        // Arrange
        $target = '8.8.8.8';
        $type = LookupType::Ip;

        $cachedResult = new LookupResult(
            uuid: 'test-uuid',
            target: $target,
            type: $type,
            provider: 'mock',
            resultData: ['ip' => $target, 'risk_score' => 10],
            lookupTimeMs: 50.0,
        );

        $cachedLookup = new CachedLookupResult(
            result: $cachedResult,
            cachedAt: now()->toIso8601String(),
            ttl: 3600,
            isStale: false,
        );

        $this->cache
            ->shouldReceive('get')
            ->once()
            ->with($target, $type)
            ->andReturn($cachedLookup);

        // Act
        $result = $this->service->lookup($target, $type, 1);

        // Assert
        $this->assertTrue($result->cached);
        $this->assertSame(0.0, $result->lookupTimeMs);
        $this->assertSame('test-uuid', $result->uuid);
        $this->assertSame($target, $result->target);
    }

    #[Test]
    public function test_calls_provider_on_cache_miss(): void
    {
        // Arrange
        $target = '8.8.8.8';
        $type = LookupType::Ip;
        $apiKeyId = 1;

        $providerResult = new LookupResult(
            uuid: 'fresh-uuid',
            target: $target,
            type: $type,
            provider: 'mock',
            resultData: ['ip' => $target, 'risk_score' => 25],
            lookupTimeMs: 120.0,
        );

        $this->cache
            ->shouldReceive('get')
            ->once()
            ->with($target, $type)
            ->andReturnNull();

        $provider = Mockery::mock(LookupProviderInterface::class);
        $provider->shouldReceive('getName')->andReturn('mock');
        $provider->shouldReceive('lookup')->andReturn($providerResult);

        $this->providerRegistry
            ->shouldReceive('getAvailableProviders')
            ->once()
            ->with($type)
            ->andReturn([$provider]);

        $this->retry
            ->shouldReceive('execute')
            ->once()
            ->andReturnUsing(fn (callable $op) => $op());

        $this->circuitBreaker
            ->shouldReceive('recordSuccess')
            ->once()
            ->with('mock');

        $this->resultRepository
            ->shouldReceive('store')
            ->once()
            ->with($providerResult, $apiKeyId);

        Event::fake([LookupCompleted::class]);

        // Act
        $result = $this->service->lookup($target, $type, $apiKeyId);

        // Assert
        $this->assertSame('fresh-uuid', $result->uuid);
        $this->assertFalse($result->cached);
        $this->assertSame(120.0, $result->lookupTimeMs);
        Event::assertDispatched(LookupCompleted::class);
    }

    #[Test]
    public function test_falls_back_to_stale_cache_when_all_providers_fail(): void
    {
        // Arrange
        $target = '8.8.8.8';
        $type = LookupType::Ip;

        $this->cache
            ->shouldReceive('get')
            ->once()
            ->andReturnNull();

        $provider = Mockery::mock(LookupProviderInterface::class);
        $provider->shouldReceive('getName')->andReturn('failing');

        $this->providerRegistry
            ->shouldReceive('getAvailableProviders')
            ->once()
            ->andReturn([$provider]);

        $this->retry
            ->shouldReceive('execute')
            ->once()
            ->andThrow(new \RuntimeException('provider down'));

        $this->circuitBreaker
            ->shouldReceive('recordFailure')
            ->once()
            ->with('failing');

        Event::fake([LookupFailed::class]);
        Log::shouldReceive('warning')->once();
        Log::shouldReceive('error')->once();
        Log::shouldReceive('info')->once();

        $staleResult = new LookupResult(
            uuid: 'stale-uuid',
            target: $target,
            type: $type,
            provider: 'mock',
            resultData: ['ip' => $target, 'risk_score' => 5],
            lookupTimeMs: 80.0,
        );

        $this->cache
            ->shouldReceive('getStale')
            ->once()
            ->with($target, $type)
            ->andReturn($staleResult);

        // Act
        $result = $this->service->lookup($target, $type, 1);

        // Assert
        $this->assertTrue($result->cached);
        $this->assertSame(0.0, $result->lookupTimeMs);
        $this->assertSame('stale-uuid', $result->uuid);
    }

    #[Test]
    public function test_throws_when_no_providers_and_no_stale_cache(): void
    {
        // Arrange
        $target = '8.8.8.8';
        $type = LookupType::Ip;

        $this->cache
            ->shouldReceive('get')
            ->once()
            ->andReturnNull();

        $this->providerRegistry
            ->shouldReceive('getAvailableProviders')
            ->once()
            ->andReturn([]);

        Log::shouldReceive('warning')->once();
        Log::shouldReceive('error')->once();

        $this->cache
            ->shouldReceive('getStale')
            ->once()
            ->with($target, $type)
            ->andReturnNull();

        // Act & Assert
        $this->expectException(ProviderUnavailableException::class);
        $this->expectExceptionMessage('All providers are unavailable and no cached data exists.');

        $this->service->lookup($target, $type, 1);
    }

    #[Test]
    public function test_records_circuit_breaker_success(): void
    {
        // Arrange
        $target = '1.2.3.4';
        $type = LookupType::Ip;

        $providerResult = new LookupResult(
            uuid: 'uuid-success',
            target: $target,
            type: $type,
            provider: 'test-provider',
            resultData: ['ip' => $target],
            lookupTimeMs: 10.0,
        );

        $this->cache->shouldReceive('get')->once()->andReturnNull();

        $provider = Mockery::mock(LookupProviderInterface::class);
        $provider->shouldReceive('getName')->andReturn('test-provider');
        $provider->shouldReceive('lookup')->andReturn($providerResult);

        $this->providerRegistry
            ->shouldReceive('getAvailableProviders')
            ->once()
            ->andReturn([$provider]);

        $this->retry
            ->shouldReceive('execute')
            ->once()
            ->andReturnUsing(fn (callable $op) => $op());

        $this->circuitBreaker
            ->shouldReceive('recordSuccess')
            ->once()
            ->with('test-provider');

        $this->resultRepository->shouldReceive('store')->once();
        Event::fake([LookupCompleted::class]);

        // Act
        $result = $this->service->lookup($target, $type, 1);

        // Assert
        $this->assertSame('uuid-success', $result->uuid);
        $this->assertSame('test-provider', $result->provider);
        Event::assertDispatched(LookupCompleted::class);
    }

    #[Test]
    public function test_records_circuit_breaker_failure(): void
    {
        // Arrange
        $target = '1.2.3.4';
        $type = LookupType::Ip;

        $this->cache->shouldReceive('get')->once()->andReturnNull();

        $provider = Mockery::mock(LookupProviderInterface::class);
        $provider->shouldReceive('getName')->andReturn('failing-provider');

        $this->providerRegistry
            ->shouldReceive('getAvailableProviders')
            ->once()
            ->andReturn([$provider]);

        $this->retry
            ->shouldReceive('execute')
            ->once()
            ->andThrow(new \RuntimeException('connection timeout'));

        $this->circuitBreaker
            ->shouldReceive('recordFailure')
            ->once()
            ->with('failing-provider');

        Event::fake([LookupFailed::class]);
        Log::shouldReceive('warning')->once();
        Log::shouldReceive('error')->once();
        Log::shouldReceive('info')->once();

        $staleResult = new LookupResult(
            uuid: 'stale',
            target: $target,
            type: $type,
            provider: 'old',
            resultData: [],
            lookupTimeMs: 0.0,
        );

        $this->cache
            ->shouldReceive('getStale')
            ->once()
            ->andReturn($staleResult);

        // Act
        $result = $this->service->lookup($target, $type, 1);

        // Assert
        $this->assertTrue($result->cached);
        $this->assertSame('stale', $result->uuid);
    }
}
