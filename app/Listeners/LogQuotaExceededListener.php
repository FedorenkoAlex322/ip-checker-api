<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\QuotaExceeded;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

final class LogQuotaExceededListener implements ShouldQueue
{
    public function handle(QuotaExceeded $event): void
    {
        Log::warning('Quota exceeded', [
            'api_key_id' => $event->apiKeyId,
            'quota_type' => $event->quotaType,
            'current_usage' => $event->currentUsage,
            'limit' => $event->limit,
        ]);
    }
}
