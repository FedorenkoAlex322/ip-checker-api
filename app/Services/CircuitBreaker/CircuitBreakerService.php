<?php

declare(strict_types=1);

namespace App\Services\CircuitBreaker;

use App\Contracts\CircuitBreakerInterface;
use App\Enums\CircuitState;
use Illuminate\Support\Facades\Redis;

final class CircuitBreakerService implements CircuitBreakerInterface
{
    private readonly string $prefix;

    public function __construct()
    {
        $this->prefix = config('circuit-breaker.redis_prefix', 'circuit');
    }

    public function isAvailable(string $service): bool
    {
        $state = $this->getState($service);

        return match ($state) {
            CircuitState::Closed, CircuitState::HalfOpen => true,
            CircuitState::Open => false,
        };
    }

    public function recordSuccess(string $service): void
    {
        $this->atomic($service, function (array $data) use ($service): array {
            $state = CircuitState::from($data['state']);

            if ($state === CircuitState::HalfOpen) {
                $data['success_count']++;
                $successThreshold = $this->getConfig($service, 'success_threshold');

                if ($data['success_count'] >= $successThreshold) {
                    $data = $this->buildClosedState();
                }
            }

            if ($state === CircuitState::Closed) {
                $data['failure_count'] = 0;
            }

            return $data;
        });
    }

    public function recordFailure(string $service): void
    {
        $this->atomic($service, function (array $data) use ($service): array {
            $state = CircuitState::from($data['state']);

            if ($state === CircuitState::HalfOpen) {
                $data['state'] = CircuitState::Open->value;
                $data['opened_at'] = now()->timestamp;
                $data['failure_count'] = 1;
                $data['success_count'] = 0;

                return $data;
            }

            $data['failure_count']++;
            $data['last_failure_at'] = now()->timestamp;

            $threshold = $this->getConfig($service, 'failure_threshold');

            if ($data['failure_count'] >= $threshold) {
                $data['state'] = CircuitState::Open->value;
                $data['opened_at'] = now()->timestamp;
                $data['success_count'] = 0;
            }

            return $data;
        });
    }

    public function getState(string $service): CircuitState
    {
        $data = $this->getData($service);
        $state = CircuitState::from($data['state']);

        if ($state === CircuitState::Open) {
            $recoveryTimeout = $this->getConfig($service, 'recovery_timeout');
            $openedAt = (int) $data['opened_at'];

            if ($openedAt > 0 && (time() - $openedAt) >= $recoveryTimeout) {
                $data['state'] = CircuitState::HalfOpen->value;
                $data['success_count'] = 0;
                $this->saveData($service, $data);

                return CircuitState::HalfOpen;
            }
        }

        return $state;
    }

    public function reset(string $service): void
    {
        $this->saveData($service, $this->buildClosedState());
    }

    /**
     * @return array<string, CircuitState>
     */
    public function getAllStates(): array
    {
        $pattern = $this->prefix . ':*';
        $prefixLength = strlen($this->prefix) + 1;
        $states = [];

        /** @var \Illuminate\Redis\Connections\Connection $connection */
        $connection = Redis::connection();
        $cursor = null;

        do {
            /** @var array{0: string, 1: string[]} $result */
            $result = $connection->command('scan', [$cursor ?? '0', 'MATCH', $pattern, 'COUNT', '100']);
            $cursor = $result[0];
            $keys = $result[1];

            foreach ($keys as $key) {
                $serviceName = substr((string) $key, $prefixLength);
                $states[$serviceName] = $this->getState($serviceName);
            }
        } while ((string) $cursor !== '0');

        return $states;
    }

    /**
     * Execute a read-modify-write cycle atomically using Redis WATCH/MULTI/EXEC.
     *
     * @param string $service
     * @param callable(array): array $callback
     */
    private function atomic(string $service, callable $callback): void
    {
        $key = $this->getRedisKey($service);

        /** @var \Illuminate\Redis\Connections\Connection $connection */
        $connection = Redis::connection();

        $retries = 3;

        while ($retries > 0) {
            $connection->command('watch', [$key]);

            $raw = $connection->command('get', [$key]);
            $data = $raw !== null ? json_decode((string) $raw, true) : $this->buildClosedState();

            $updated = $callback($data);

            $results = $connection->command('multi');
            $connection->command('set', [$key, json_encode($updated)]);

            /** @var array|null $execResult */
            $execResult = $connection->command('exec');

            if ($execResult !== null) {
                return;
            }

            $retries--;
        }

        // Fallback: save without transaction if all retries exhausted
        $data = $this->getData($service);
        /** @var array{state: string, failure_count: int, success_count: int, last_failure_at: int|null, opened_at: int|null} $updated */
        $updated = $callback($data);
        $this->saveData($service, $updated);
    }

    /**
     * @return array{state: string, failure_count: int, success_count: int, last_failure_at: int|null, opened_at: int|null}
     */
    private function getData(string $service): array
    {
        $key = $this->getRedisKey($service);
        $raw = Redis::get($key);

        if ($raw === null) {
            return $this->buildClosedState();
        }

        /** @var array{state: string, failure_count: int, success_count: int, last_failure_at: int|null, opened_at: int|null} $decoded */
        $decoded = json_decode((string) $raw, true);

        return $decoded;
    }

    /**
     * @param array{state: string, failure_count: int, success_count: int, last_failure_at: int|null, opened_at: int|null} $data
     */
    private function saveData(string $service, array $data): void
    {
        $key = $this->getRedisKey($service);
        Redis::set($key, json_encode($data));
    }

    private function getConfig(string $service, string $key): int
    {
        /** @var int|null $providerValue */
        $providerValue = config("circuit-breaker.providers.{$service}.{$key}");

        if ($providerValue !== null) {
            return $providerValue;
        }

        /** @var int $defaultValue */
        $defaultValue = config("circuit-breaker.default.{$key}");

        return $defaultValue;
    }

    private function getRedisKey(string $service): string
    {
        return $this->prefix . ':' . $service;
    }

    /**
     * @return array{state: string, failure_count: int, success_count: int, last_failure_at: null, opened_at: null}
     */
    private function buildClosedState(): array
    {
        return [
            'state' => CircuitState::Closed->value,
            'failure_count' => 0,
            'success_count' => 0,
            'last_failure_at' => null,
            'opened_at' => null,
        ];
    }
}
