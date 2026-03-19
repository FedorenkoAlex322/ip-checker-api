<?php

namespace App\Listeners;

use App\Contracts\LookupCacheInterface;
use App\Events\LookupCompleted;

final class CacheResultListener
{
    public function __construct(
        private readonly LookupCacheInterface $lookupCache,
    ) {}

    public function handle(LookupCompleted $event): void
    {
        $this->lookupCache->put(
            $event->result->target,
            $event->result->type,
            $event->result,
        );
    }
}
