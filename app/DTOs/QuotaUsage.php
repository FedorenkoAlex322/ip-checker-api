<?php

namespace App\DTOs;

final readonly class QuotaUsage
{
    public function __construct(
        public int $apiKeyId,
        public string $date,
        public int $requestCount,
    ) {}

    public function toArray(): array
    {
        return [
            'api_key_id' => $this->apiKeyId,
            'date' => $this->date,
            'request_count' => $this->requestCount,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            apiKeyId: (int) $data['api_key_id'],
            date: $data['date'],
            requestCount: (int) $data['request_count'],
        );
    }
}
