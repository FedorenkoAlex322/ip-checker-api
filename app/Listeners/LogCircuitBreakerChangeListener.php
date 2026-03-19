<?php

namespace App\Listeners;

use App\Events\CircuitBreakerStateChanged;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

final class LogCircuitBreakerChangeListener implements ShouldQueue
{
    public function handle(CircuitBreakerStateChanged $event): void
    {
        Log::warning('Circuit breaker state changed', [
            'service' => $event->serviceName,
            'previous_state' => $event->previousState->value,
            'new_state' => $event->newState->value,
        ]);
    }
}
