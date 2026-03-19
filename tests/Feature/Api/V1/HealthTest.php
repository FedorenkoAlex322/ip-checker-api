<?php

namespace Tests\Feature\Api\V1;

use App\Contracts\CircuitBreakerInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

final class HealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockCircuitBreaker();
        $this->mockRedis();
    }

    // ------------------------------------------------------------------
    // No auth required
    // ------------------------------------------------------------------

    public function test_health_endpoint_without_auth(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk();
    }

    // ------------------------------------------------------------------
    // Response structure
    // ------------------------------------------------------------------

    public function test_health_response_structure(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'status',
                    'services',
                    'circuit_breakers',
                    'timestamp',
                ],
            ]);
    }

    public function test_health_reports_healthy_when_services_are_up(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('data.status', 'healthy');
    }

    public function test_health_reports_degraded_when_redis_is_down(): void
    {
        // Swap the Redis facade so that ping() throws
        Redis::swap(
            Mockery::mock('Redis')
                ->shouldReceive('ping')
                ->andThrow(new \RuntimeException('Connection refused'))
                ->getMock()
        );

        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('data.status', 'degraded')
            ->assertJsonPath('data.services.redis', 'down');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function mockCircuitBreaker(): void
    {
        $mock = Mockery::mock(CircuitBreakerInterface::class);
        $mock->shouldReceive('getAllStates')->andReturn([]);
        $this->app->instance(CircuitBreakerInterface::class, $mock);
    }

    private function mockRedis(): void
    {
        Redis::shouldReceive('ping')->andReturn(true);
    }
}
