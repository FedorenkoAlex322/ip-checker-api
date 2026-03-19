<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\LookupType;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class LookupFailed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $target,
        public readonly LookupType $type,
        public readonly int $apiKeyId,
        public readonly string $errorMessage,
        public readonly ?string $providerName = null,
    ) {}
}
