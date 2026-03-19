<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class QuotaExceeded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly int $apiKeyId,
        public readonly string $quotaType,
        public readonly int $currentUsage,
        public readonly int $limit,
    ) {}
}
