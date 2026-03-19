<?php

namespace App\Services\Providers;

use App\Contracts\LookupProviderInterface;
use App\DTOs\LookupResult;
use App\Enums\LookupType;
use Illuminate\Support\Str;

abstract class AbstractLookupProvider implements LookupProviderInterface
{
    /**
     * Perform the actual lookup logic.
     *
     * @return array<string, mixed>
     */
    abstract protected function doLookup(string $target, LookupType $type): array;

    /**
     * @return array<LookupType>
     */
    abstract protected function supportedTypes(): array;

    public function lookup(string $target, LookupType $type): LookupResult
    {
        $startTime = microtime(true);

        $data = $this->doLookup($target, $type);

        $elapsed = (microtime(true) - $startTime) * 1000; // ms

        return new LookupResult(
            uuid: Str::uuid()->toString(),
            target: $target,
            type: $type,
            provider: $this->getName(),
            resultData: $data,
            lookupTimeMs: round($elapsed, 2),
        );
    }

    public function supports(LookupType $type): bool
    {
        return in_array($type, $this->supportedTypes(), true);
    }

    abstract public function getName(): string;

    abstract public function getPriority(): int;

    public function isEnabled(): bool
    {
        return true;
    }
}
