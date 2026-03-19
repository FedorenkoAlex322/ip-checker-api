<?php

namespace Tests\Feature\Middleware;

use App\Contracts\RateLimiterInterface;
use App\DTOs\RateLimitResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;
use Tests\Traits\MocksRedisServices;

final class RateLimitTest extends TestCase
{
    use MocksRedisServices;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpApiKeyAndMocks();
    }

    // ------------------------------------------------------------------
    // Headers
    // ------------------------------------------------------------------

    public function test_adds_rate_limit_headers(): void
    {
        $response = $this->getJson('/api/v1/quota', $this->apiKeyHeaders());

        $response->assertOk()
            ->assertHeader('X-RateLimit-Limit', '60')
            ->assertHeader('X-RateLimit-Remaining', '59')
            ->assertHeader('X-RateLimit-Reset');
    }

    // ------------------------------------------------------------------
    // Rate limited
    // ------------------------------------------------------------------

    public function test_returns_429_when_rate_limited(): void
    {
        // Override the rate limiter mock to deny the request
        $this->mockRateLimiter(allowed: false, retryAfter: 30);

        $response = $this->getJson('/api/v1/quota', $this->apiKeyHeaders());

        $response->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED')
            ->assertHeader('Retry-After', '30');
    }

    public function test_returns_429_with_retry_after_value(): void
    {
        $this->mockRateLimiter(allowed: false, retryAfter: 45);

        $response = $this->getJson('/api/v1/quota', $this->apiKeyHeaders());

        $response->assertStatus(429)
            ->assertHeader('Retry-After', '45');
    }

    // ------------------------------------------------------------------
    // Allowed requests still include headers
    // ------------------------------------------------------------------

    public function test_successful_request_includes_remaining_count(): void
    {
        // Use a custom mock with specific remaining count
        $mock = Mockery::mock(RateLimiterInterface::class);
        $mock->shouldReceive('attempt')->andReturn(new RateLimitResult(
            allowed: true,
            limit: 300,
            remaining: 250,
            retryAfter: 0,
            resetAt: time() + 60,
        ));
        $mock->shouldReceive('remaining')->andReturn(250);
        $mock->shouldReceive('clear')->andReturnNull();
        $this->app->instance(RateLimiterInterface::class, $mock);

        $response = $this->getJson('/api/v1/quota', $this->apiKeyHeaders());

        $response->assertOk()
            ->assertHeader('X-RateLimit-Limit', '300')
            ->assertHeader('X-RateLimit-Remaining', '250');
    }
}
