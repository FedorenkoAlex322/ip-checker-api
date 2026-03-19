<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\CircuitState;

interface CircuitBreakerInterface
{
    public function isAvailable(string $service): bool;

    public function recordSuccess(string $service): void;

    public function recordFailure(string $service): void;

    public function getState(string $service): CircuitState;

    public function reset(string $service): void;

    /**
     * @return array<string, CircuitState>
     */
    public function getAllStates(): array;
}
