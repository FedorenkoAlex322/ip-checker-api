<?php

namespace Tests\Feature\Api\V1;

use App\Contracts\LookupProviderInterface;
use App\Contracts\ProviderRegistryInterface;
use App\Contracts\RetryableInterface;
use App\DTOs\LookupResult;
use App\Enums\LookupType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;
use Tests\Traits\MocksRedisServices;

final class IpLookupTest extends TestCase
{
    use MocksRedisServices;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpApiKeyAndMocks();
        $this->mockLookupProviderChain();
    }

    // ------------------------------------------------------------------
    // Success
    // ------------------------------------------------------------------

    public function test_successful_ip_lookup(): void
    {
        $response = $this->postJson('/api/v1/lookup/ip', [
            'target' => '8.8.8.8',
        ], $this->apiKeyHeaders());

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['uuid', 'target', 'type', 'result', 'cached'],
                'meta' => ['provider', 'lookup_time_ms', 'cached'],
            ]);
    }

    public function test_successful_ipv6_lookup(): void
    {
        $response = $this->postJson('/api/v1/lookup/ip', [
            'target' => '2001:4860:4860::8888',
        ], $this->apiKeyHeaders());

        $response->assertOk();
    }

    // ------------------------------------------------------------------
    // Validation
    // ------------------------------------------------------------------

    public function test_returns_422_for_invalid_ip(): void
    {
        $response = $this->postJson('/api/v1/lookup/ip', [
            'target' => 'not-an-ip',
        ], $this->apiKeyHeaders());

        $response->assertStatus(422)
            ->assertJsonStructure(['error' => ['code', 'message', 'details']]);
    }

    public function test_returns_422_without_target(): void
    {
        $response = $this->postJson('/api/v1/lookup/ip', [], $this->apiKeyHeaders());

        $response->assertStatus(422)
            ->assertJsonStructure(['error' => ['code', 'message', 'details']]);
    }

    public function test_returns_422_for_domain_as_ip(): void
    {
        $response = $this->postJson('/api/v1/lookup/ip', [
            'target' => 'example.com',
        ], $this->apiKeyHeaders());

        $response->assertStatus(422);
    }

    // ------------------------------------------------------------------
    // Auth
    // ------------------------------------------------------------------

    public function test_returns_401_without_api_key(): void
    {
        $response = $this->postJson('/api/v1/lookup/ip', ['target' => '8.8.8.8']);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'INVALID_API_KEY');
    }

    public function test_returns_401_with_invalid_api_key(): void
    {
        $response = $this->postJson('/api/v1/lookup/ip', ['target' => '8.8.8.8'], [
            'X-API-Key' => 'completely-wrong-key',
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    // Response contract
    // ------------------------------------------------------------------

    public function test_response_contains_correct_target_and_type(): void
    {
        $response = $this->postJson('/api/v1/lookup/ip', [
            'target' => '1.1.1.1',
        ], $this->apiKeyHeaders());

        $response->assertOk()
            ->assertJsonPath('data.target', '1.1.1.1')
            ->assertJsonPath('data.type', 'ip');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Mock the provider registry + retry so the lookup service returns a
     * predictable result without hitting external APIs.
     */
    private function mockLookupProviderChain(): void
    {
        $providerMock = Mockery::mock(LookupProviderInterface::class);
        $providerMock->shouldReceive('getName')->andReturn('mock');
        $providerMock->shouldReceive('supports')->andReturn(true);
        $providerMock->shouldReceive('lookup')->andReturnUsing(
            fn (string $target, LookupType $type) => new LookupResult(
                uuid: Str::uuid()->toString(),
                target: $target,
                type: $type,
                provider: 'mock',
                resultData: ['ip' => $target, 'country' => 'US'],
                lookupTimeMs: 42.5,
                cached: false,
            )
        );

        $registryMock = Mockery::mock(ProviderRegistryInterface::class);
        $registryMock->shouldReceive('getAvailableProviders')->andReturn([$providerMock]);
        $registryMock->shouldReceive('getProvider')->andReturn($providerMock);
        $this->app->instance(ProviderRegistryInterface::class, $registryMock);

        $retryMock = Mockery::mock(RetryableInterface::class);
        $retryMock->shouldReceive('execute')->andReturnUsing(
            fn (callable $op) => $op()
        );
        $this->app->instance(RetryableInterface::class, $retryMock);
    }
}
