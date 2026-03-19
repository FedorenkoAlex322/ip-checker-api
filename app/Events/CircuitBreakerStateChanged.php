<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\CircuitState;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CircuitBreakerStateChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $serviceName,
        public readonly CircuitState $previousState,
        public readonly CircuitState $newState,
    ) {}
}
