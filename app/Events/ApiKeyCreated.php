<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\ApiKeyTier;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ApiKeyCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly int $apiKeyId,
        public readonly string $name,
        public readonly ApiKeyTier $tier,
    ) {}
}
