<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class RetryConfig
{
    public function __construct(
        public int $maxRetries = 3,
        public int $baseDelayMs = 200,
        public int $maxDelayMs = 5000,
        public float $multiplier = 2.0,
        public bool $jitterEnabled = true,
        public array $retryableExceptions = [],
    ) {}

    public function toArray(): array
    {
        return [
            'max_retries' => $this->maxRetries,
            'base_delay_ms' => $this->baseDelayMs,
            'max_delay_ms' => $this->maxDelayMs,
            'multiplier' => $this->multiplier,
            'jitter_enabled' => $this->jitterEnabled,
            'retryable_exceptions' => $this->retryableExceptions,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            maxRetries: (int) ($data['max_retries'] ?? 3),
            baseDelayMs: (int) ($data['base_delay_ms'] ?? 200),
            maxDelayMs: (int) ($data['max_delay_ms'] ?? 5000),
            multiplier: (float) ($data['multiplier'] ?? 2.0),
            jitterEnabled: (bool) ($data['jitter_enabled'] ?? true),
            retryableExceptions: $data['retryable_exceptions'] ?? [],
        );
    }
}
