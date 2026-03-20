<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\CircuitBreakerInterface;
use App\Contracts\ProviderRegistryInterface;
use App\Enums\CircuitState;
use App\Http\Controllers\Controller;
use App\Http\Resources\HealthResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

final class HealthController extends Controller
{
    public function __construct(
        private readonly CircuitBreakerInterface $circuitBreaker,
        private readonly ProviderRegistryInterface $registry,
    ) {}

    public function show(): JsonResponse
    {
        $mysqlStatus = $this->checkMysql();
        $redisStatus = $this->checkRedis();

        $circuitBreakers = [];
        foreach ($this->circuitBreaker->getAllStates() as $service => $state) {
            $circuitBreakers[$service] = $state->value;
        }

        $providers = $this->getProviderStatuses();

        $hasCircuitOpen = in_array('circuit_open', array_column($providers, 'status'), true);

        $overallStatus = match (true) {
            $mysqlStatus !== 'up' || $redisStatus !== 'up' => 'degraded',
            $hasCircuitOpen => 'degraded',
            default => 'healthy',
        };

        return (new HealthResource([
            'status' => $overallStatus,
            'services' => [
                'mysql' => $mysqlStatus,
                'redis' => $redisStatus,
            ],
            'circuit_breakers' => $circuitBreakers,
            'providers' => $providers,
            'timestamp' => now(),
        ]))->response();
    }

    /**
     * @return array<string, array{status: string, reason?: string}>
     */
    private function getProviderStatuses(): array
    {
        $providers = [];

        foreach ($this->registry->getAllProviders() as $provider) {
            $status = 'active';
            $reason = null;

            if (! $provider->isEnabled()) {
                $status = 'misconfigured';
                $reason = 'API key missing';
            } elseif ($this->circuitBreaker->getState($provider->getName()) === CircuitState::Open) {
                $status = 'circuit_open';
                $reason = 'Circuit breaker is open';
            }

            $providers[$provider->getName()] = array_filter([
                'status' => $status,
                'reason' => $reason,
            ]);
        }

        return $providers;
    }

    private function checkMysql(): string
    {
        try {
            DB::connection()->getPdo();

            return 'up';
        } catch (\Throwable) {
            return 'down';
        }
    }

    private function checkRedis(): string
    {
        try {
            Redis::ping();

            return 'up';
        } catch (\Throwable) {
            return 'down';
        }
    }
}
