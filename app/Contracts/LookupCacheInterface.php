<?php

namespace App\Contracts;

use App\DTOs\CachedLookupResult;
use App\DTOs\LookupResult;
use App\Enums\LookupType;

interface LookupCacheInterface
{
    public function get(string $target, LookupType $type): ?CachedLookupResult;

    public function put(string $target, LookupType $type, LookupResult $result): void;

    public function forget(string $target, LookupType $type): void;

    public function getStale(string $target, LookupType $type): ?LookupResult;

    public function generateKey(string $target, LookupType $type): string;
}
