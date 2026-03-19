<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\LookupCacheInterface;
use App\Contracts\ProviderRegistryInterface;
use App\Enums\LookupType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class RefreshStaleCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 10;

    public function __construct(
        public readonly string $target,
        public readonly LookupType $type,
    ) {}

    public function handle(
        ProviderRegistryInterface $providerRegistry,
        LookupCacheInterface $cache,
    ): void {
        try {
            $provider = $providerRegistry->getProvider($this->type);
            $result = $provider->lookup($this->target, $this->type);
            $cache->put($this->target, $this->type, $result);

            Log::debug('Stale cache refreshed', [
                'target' => $this->target,
                'type' => $this->type->value,
                'provider' => $provider->getName(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to refresh stale cache', [
                'target' => $this->target,
                'type' => $this->type->value,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
