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

final class DomainLookupTest extends TestCase
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

    public function test_successful_domain_lookup(): void
    {
        $response = $this->postJson('/api/v1/lookup/domain', [
            'target' => 'example.com',
        ], $this->apiKeyHeaders());

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['uuid', 'target', 'type', 'result', 'cached'],
                'meta' => ['provider', 'lookup_time_ms', 'cached'],
            ]);
    }

    public function test_successful_subdomain_lookup(): void
    {
        $response = $this->postJson('/api/v1/lookup/domain', [
            'target' => 'sub.example.com',
        ], $this->apiKeyHeaders());

        $response->assertOk()
            ->assertJsonPath('data.target', 'sub.example.com')
            ->assertJsonPath('data.type', 'domain');
    }

    // ------------------------------------------------------------------
    // Validation
    // ------------------------------------------------------------------

    public function test_returns_422_for_invalid_domain(): void
    {
        $response = $this->postJson('/api/v1/lookup/domain', [
            'target' => 'not a domain!!!',
        ], $this->apiKeyHeaders());

        $response->assertStatus(422)
            ->assertJsonStructure(['error' => ['code', 'message', 'details']]);
    }

    public function test_returns_422_for_ip_as_domain(): void
    {
        $response = $this->postJson('/api/v1/lookup/domain', [
            'target' => '8.8.8.8',
        ], $this->apiKeyHeaders());

        $response->assertStatus(422);
    }

    public function test_returns_422_without_target(): void
    {
        $response = $this->postJson('/api/v1/lookup/domain', [], $this->apiKeyHeaders());

        $response->assertStatus(422);
    }

    // ------------------------------------------------------------------
    // Auth
    // ------------------------------------------------------------------

    public function test_returns_401_without_api_key(): void
    {
        $response = $this->postJson('/api/v1/lookup/domain', [
            'target' => 'example.com',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'INVALID_API_KEY');
    }

    public function test_returns_401_with_invalid_api_key(): void
    {
        $response = $this->postJson('/api/v1/lookup/domain', [
            'target' => 'example.com',
        ], ['X-API-Key' => 'wrong-key']);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

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
                resultData: ['domain' => $target, 'registrar' => 'Test Registrar'],
                lookupTimeMs: 35.0,
                cached: false,
            )
        );

        $registryMock = Mockery::mock(ProviderRegistryInterface::class);
        $registryMock->shouldReceive('getAvailableProviders')->andReturn([$providerMock]);
        $registryMock->shouldReceive('getProvider')->andReturn($providerMock);
        $this->app->instance(ProviderRegistryInterface::class, $registryMock);

        $retryMock = Mockery::mock(RetryableInterface::class);
        $retryMock->shouldReceive('execute')->andReturnUsing(fn (callable $op) => $op());
        $this->app->instance(RetryableInterface::class, $retryMock);
    }
}
