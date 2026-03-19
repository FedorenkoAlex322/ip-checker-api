<?php

namespace App\Listeners;

use App\Events\LookupCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

final class LogLookupListener implements ShouldQueue
{
    public function handle(LookupCompleted $event): void
    {
        Log::info('Lookup completed', [
            'target' => $event->result->target,
            'type' => $event->result->type->value,
            'provider' => $event->result->provider,
            'lookup_time_ms' => $event->result->lookupTimeMs,
            'api_key_id' => $event->apiKeyId,
        ]);
    }
}
