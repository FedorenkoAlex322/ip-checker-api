<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\LookupType;

final readonly class LookupResult
{
    public function __construct(
        public string $uuid,
        public string $target,
        public LookupType $type,
        public string $provider,
        public array $resultData,
        public float $lookupTimeMs,
        public bool $cached = false,
    ) {}

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'target' => $this->target,
            'type' => $this->type->value,
            'provider' => $this->provider,
            'result_data' => $this->resultData,
            'lookup_time_ms' => $this->lookupTimeMs,
            'cached' => $this->cached,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            uuid: $data['uuid'],
            target: $data['target'],
            type: LookupType::from($data['type']),
            provider: $data['provider'],
            resultData: $data['result_data'],
            lookupTimeMs: (float) $data['lookup_time_ms'],
            cached: $data['cached'] ?? false,
        );
    }
}
