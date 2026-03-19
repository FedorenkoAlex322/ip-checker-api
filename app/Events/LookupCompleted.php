<?php

declare(strict_types=1);

namespace App\Events;

use App\DTOs\LookupResult;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class LookupCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly LookupResult $result,
        public readonly int $apiKeyId,
    ) {}
}
