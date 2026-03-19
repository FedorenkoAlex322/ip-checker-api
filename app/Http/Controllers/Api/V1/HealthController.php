<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\CircuitBreakerInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\HealthResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

final class HealthController extends Controller
{
    public function __construct(
        private readonly CircuitBreakerInterface $circuitBreaker,
    ) {}

    public function show(): JsonResponse
    {
        $mysqlStatus = $this->checkMysql();
        $redisStatus = $this->checkRedis();

        $circuitBreakers = [];
        foreach ($this->circuitBreaker->getAllStates() as $service => $state) {
            $circuitBreakers[$service] = $state->value;
        }

        $overallStatus = ($mysqlStatus === 'up' && $redisStatus === 'up')
            ? 'healthy'
            : 'degraded';

        return (new HealthResource([
            'status' => $overallStatus,
            'services' => [
                'mysql' => $mysqlStatus,
                'redis' => $redisStatus,
            ],
            'circuit_breakers' => $circuitBreakers,
            'timestamp' => now(),
        ]))->response();
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
