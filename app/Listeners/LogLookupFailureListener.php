<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\LookupFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

final class LogLookupFailureListener implements ShouldQueue
{
    public function handle(LookupFailed $event): void
    {
        Log::error('Lookup failed', [
            'target' => $event->target,
            'type' => $event->type->value,
            'api_key_id' => $event->apiKeyId,
            'error_message' => $event->errorMessage,
            'provider' => $event->providerName,
        ]);
    }
}
