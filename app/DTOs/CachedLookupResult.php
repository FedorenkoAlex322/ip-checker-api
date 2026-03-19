<?php

namespace App\DTOs;

final readonly class CachedLookupResult
{
    public function __construct(
        public LookupResult $result,
        public string $cachedAt,
        public int $ttl,
        public bool $isStale,
    ) {}

    public function toArray(): array
    {
        return [
            'result' => $this->result->toArray(),
            'cached_at' => $this->cachedAt,
            'ttl' => $this->ttl,
            'is_stale' => $this->isStale,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            result: LookupResult::fromArray($data['result']),
            cachedAt: $data['cached_at'],
            ttl: (int) $data['ttl'],
            isStale: (bool) $data['is_stale'],
        );
    }
}
