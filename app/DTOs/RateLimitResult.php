<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class RateLimitResult
{
    public function __construct(
        public bool $allowed,
        public int $limit,
        public int $remaining,
        public int $retryAfter,
        public int $resetAt,
    ) {}

    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'limit' => $this->limit,
            'remaining' => $this->remaining,
            'retry_after' => $this->retryAfter,
            'reset_at' => $this->resetAt,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            allowed: (bool) $data['allowed'],
            limit: (int) $data['limit'],
            remaining: (int) $data['remaining'],
            retryAfter: (int) $data['retry_after'],
            resetAt: (int) $data['reset_at'],
        );
    }
}
