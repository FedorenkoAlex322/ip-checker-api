<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ApiKeyCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

final class LogApiKeyCreatedListener implements ShouldQueue
{
    public function handle(ApiKeyCreated $event): void
    {
        Log::info('API key created', [
            'api_key_id' => $event->apiKeyId,
            'name' => $event->name,
            'tier' => $event->tier->value,
        ]);
    }
}
