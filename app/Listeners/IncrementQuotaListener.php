<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\QuotaServiceInterface;
use App\Events\LookupCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;

final class IncrementQuotaListener implements ShouldQueue
{
    public function __construct(
        private readonly QuotaServiceInterface $quotaService,
    ) {}

    public function handle(LookupCompleted $event): void
    {
        $this->quotaService->increment($event->apiKeyId);
    }
}
