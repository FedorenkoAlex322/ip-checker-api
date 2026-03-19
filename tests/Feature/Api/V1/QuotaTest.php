<?php

namespace Tests\Feature\Api\V1;

use App\Contracts\QuotaServiceInterface;
use App\DTOs\QuotaStatus;
use App\Enums\ApiKeyTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;
use Tests\Traits\MocksRedisServices;

final class QuotaTest extends TestCase
{
    use MocksRedisServices;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpApiKeyAndMocks();
    }

    // ------------------------------------------------------------------
    // Success
    // ------------------------------------------------------------------

    public function test_returns_quota_status(): void
    {
        $response = $this->getJson('/api/v1/quota', $this->apiKeyHeaders());

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'tier',
                    'daily' => ['used', 'limit', 'remaining'],
                    'monthly' => ['used', 'limit', 'remaining'],
                ],
            ]);
    }

    public function test_quota_response_contains_correct_values(): void
    {
        // Override the default mock with specific values
        $quotaMock = Mockery::mock(QuotaServiceInterface::class);
        $quotaMock->shouldReceive('check')->andReturn(new QuotaStatus(
            allowed: true,
            tier: ApiKeyTier::Pro,
            dailyUsed: 150,
            dailyLimit: 50000,
            monthlyUsed: 3200,
            monthlyLimit: 1000000,
        ));
        $quotaMock->shouldReceive('increment')->andReturnNull();
        $quotaMock->shouldReceive('getUsage')->andReturnNull();
        $this->app->instance(QuotaServiceInterface::class, $quotaMock);

        $response = $this->getJson('/api/v1/quota', $this->apiKeyHeaders());

        $response->assertOk()
            ->assertJsonPath('data.tier', 'pro')
            ->assertJsonPath('data.daily.used', 150)
            ->assertJsonPath('data.daily.limit', 50000)
            ->assertJsonPath('data.daily.remaining', 49850)
            ->assertJsonPath('data.monthly.used', 3200)
            ->assertJsonPath('data.monthly.limit', 1000000)
            ->assertJsonPath('data.monthly.remaining', 996800);
    }

    // ------------------------------------------------------------------
    // Auth
    // ------------------------------------------------------------------

    public function test_returns_401_without_api_key(): void
    {
        $response = $this->getJson('/api/v1/quota');

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'INVALID_API_KEY');
    }

    public function test_returns_401_with_invalid_api_key(): void
    {
        $response = $this->getJson('/api/v1/quota', [
            'X-API-Key' => 'invalid-key-value',
        ]);

        $response->assertStatus(401);
    }
}
