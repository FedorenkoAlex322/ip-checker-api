<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\LookupResult;
use App\Enums\LookupType;

interface LookupProviderInterface
{
    public function lookup(string $target, LookupType $type): LookupResult;

    public function supports(LookupType $type): bool;

    public function getName(): string;

    public function getPriority(): int;

    public function isEnabled(): bool;
}
