<?php

namespace Tests\Integration;

use App\Contracts\CircuitBreakerInterface;
use App\Contracts\LookupCacheInterface;
use App\Contracts\QuotaServiceInterface;
use App\Contracts\RateLimiterInterface;
use App\DTOs\QuotaStatus;
use App\DTOs\RateLimitResult;
use App\Enums\ApiKeyTier;
use App\Events\LookupCompleted;
use App\Events\LookupFailed;
use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class LookupFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $plaintextKey;

    private ApiKey $apiKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plaintextKey = 'integration-test-key-'.bin2hex(random_bytes(16));

        $this->apiKey = ApiKey::factory()->free()->create([
            'key_hash' => hash('sha256', $this->plaintextKey),
        ]);

        $this->mockRedisServices();
    }

    public function test_full_ip_lookup_flow(): void
    {
        // Act
        $response = $this->postJson('/api/v1/lookup/ip', ['target' => '8.8.8.8'], [
            'X-API-Key' => $this->plaintextKey,
        ]);

        // Assert
        $response->assertOk();

        $response->assertJsonStructure([
            'data' => ['uuid', 'target', 'type', 'result', 'cached'],
            'meta' => ['provider', 'lookup_time_ms', 'cached'],
        ]);

        $this->assertEquals('8.8.8.8', $response->json('data.target'));
        $this->assertEquals('ip', $response->json('data.type'));
        $this->assertArrayHasKey('ip', $response->json('data.result'));
        $this->assertArrayHasKey('risk_score', $response->json('data.result'));
        $this->assertEquals('mock', $response->json('meta.provider'));

        $this->assertDatabaseHas('lookup_results', [
            'target' => '8.8.8.8',
            'type' => 'ip',
            'provider' => 'mock',
        ]);
    }

    public function test_full_domain_lookup_flow(): void
    {
        // Act
        $response = $this->postJson('/api/v1/lookup/domain', ['target' => 'example.com'], [
            'X-API-Key' => $this->plaintextKey,
        ]);

        // Assert
        $response->assertOk();

        $this->assertEquals('example.com', $response->json('data.target'));
        $this->assertEquals('domain', $response->json('data.type'));
        $this->assertArrayHasKey('domain', $response->json('data.result'));
        $this->assertArrayHasKey('risk_score', $response->json('data.result'));
        $this->assertArrayHasKey('dns_records', $response->json('data.result'));
        $this->assertEquals('mock', $response->json('meta.provider'));

        $this->assertDatabaseHas('lookup_results', [
            'target' => 'example.com',
            'type' => 'domain',
            'provider' => 'mock',
        ]);
    }

    public function test_full_email_lookup_flow(): void
    {
        // Act — use a real domain so email:rfc,dns validation passes
        $response = $this->postJson('/api/v1/lookup/email', ['target' => 'test@gmail.com'], [
            'X-API-Key' => $this->plaintextKey,
        ]);

        // Assert
        $response->assertOk();

        $this->assertEquals('test@gmail.com', $response->json('data.target'));
        $this->assertEquals('email', $response->json('data.type'));
        $this->assertArrayHasKey('email', $response->json('data.result'));
        $this->assertArrayHasKey('risk_score', $response->json('data.result'));
        $this->assertArrayHasKey('is_disposable', $response->json('data.result'));
        $this->assertEquals('mock', $response->json('meta.provider'));

        $this->assertDatabaseHas('lookup_results', [
            'target' => 'test@gmail.com',
            'type' => 'email',
            'provider' => 'mock',
        ]);
    }

    public function test_deterministic_mock_results(): void
    {
        // Act — perform two lookups for the same target
        $response1 = $this->postJson('/api/v1/lookup/ip', ['target' => '1.2.3.4'], [
            'X-API-Key' => $this->plaintextKey,
        ]);
        $response2 = $this->postJson('/api/v1/lookup/ip', ['target' => '1.2.3.4'], [
            'X-API-Key' => $this->plaintextKey,
        ]);

        // Assert — both should return identical result data
        $response1->assertOk();
        $response2->assertOk();

        $result1 = $response1->json('data.result');
        $result2 = $response2->json('data.result');

        $this->assertEquals($result1['ip'], $result2['ip']);
        $this->assertEquals($result1['risk_score'], $result2['risk_score']);
        $this->assertEquals($result1['is_vpn'], $result2['is_vpn']);
        $this->assertEquals($result1['country'], $result2['country']);
        $this->assertEquals($result1['isp'], $result2['isp']);
    }

    public function test_lookup_history_after_multiple_lookups(): void
    {
        // Arrange — perform 3 different lookups
        $this->postJson('/api/v1/lookup/ip', ['target' => '8.8.8.8'], [
            'X-API-Key' => $this->plaintextKey,
        ])->assertOk();

        $this->postJson('/api/v1/lookup/domain', ['target' => 'example.com'], [
            'X-API-Key' => $this->plaintextKey,
        ])->assertOk();

        $this->postJson('/api/v1/lookup/ip', ['target' => '1.1.1.1'], [
            'X-API-Key' => $this->plaintextKey,
        ])->assertOk();

        // Act
        $response = $this->getJson('/api/v1/lookup/history', [
            'X-API-Key' => $this->plaintextKey,
        ]);

        // Assert
        $response->assertOk();

        $json = $response->json();

        $this->assertArrayHasKey('data', $json);
        $this->assertCount(3, $json['data']);

        $this->assertArrayHasKey('meta', $json);
        $this->assertEquals(3, $json['meta']['total']);
    }

    public function test_show_lookup_by_uuid(): void
    {
        // Arrange — perform a lookup and extract UUID
        $lookupResponse = $this->postJson('/api/v1/lookup/ip', ['target' => '10.0.0.1'], [
            'X-API-Key' => $this->plaintextKey,
        ]);

        $lookupResponse->assertOk();

        $uuid = $lookupResponse->json('data.uuid');
        $this->assertNotNull($uuid, 'Lookup response must contain a UUID');

        // Act
        $response = $this->getJson("/api/v1/lookup/{$uuid}", [
            'X-API-Key' => $this->plaintextKey,
        ]);

        // Assert
        $response->assertOk();

        $this->assertEquals($uuid, $response->json('data.uuid'));
        $this->assertEquals('10.0.0.1', $response->json('data.target'));
        $this->assertEquals('ip', $response->json('data.type'));
        $this->assertEquals('mock', $response->json('meta.provider'));
    }

    private function mockRedisServices(): void
    {
        // Mock rate limiter — always allow
        $rateLimiter = \Mockery::mock(RateLimiterInterface::class);
        $rateLimiter->shouldReceive('attempt')->andReturn(new RateLimitResult(
            allowed: true,
            limit: 60,
            remaining: 59,
            retryAfter: 0,
            resetAt: time() + 60,
        ));
        $this->app->instance(RateLimiterInterface::class, $rateLimiter);

        // Mock quota — always allow, never exhausted
        $quota = \Mockery::mock(QuotaServiceInterface::class);
        $quota->shouldReceive('check')->andReturn(new QuotaStatus(
            allowed: true,
            tier: ApiKeyTier::Free,
            dailyUsed: 0,
            dailyLimit: 1000,
            monthlyUsed: 0,
            monthlyLimit: 10000,
        ));
        $quota->shouldReceive('increment')->andReturnNull();
        $this->app->instance(QuotaServiceInterface::class, $quota);

        // Mock cache — no caching so every request hits the real MockProvider
        $cache = \Mockery::mock(LookupCacheInterface::class);
        $cache->shouldReceive('get')->andReturnNull();
        $cache->shouldReceive('getStale')->andReturnNull();
        $cache->shouldReceive('put')->andReturnNull();
        $this->app->instance(LookupCacheInterface::class, $cache);

        // Mock circuit breaker — all providers always available
        $cb = \Mockery::mock(CircuitBreakerInterface::class);
        $cb->shouldReceive('isAvailable')->andReturn(true);
        $cb->shouldReceive('recordSuccess')->andReturnNull();
        $cb->shouldReceive('recordFailure')->andReturnNull();
        $cb->shouldReceive('getAllStates')->andReturn([]);
        $this->app->instance(CircuitBreakerInterface::class, $cb);

        // Fake events to prevent listeners from calling real Redis
        Event::fake([LookupCompleted::class, LookupFailed::class]);
    }
}
