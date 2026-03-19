<?php

namespace App\DTOs;

use App\Enums\ApiKeyTier;

final readonly class QuotaStatus
{
    public function __construct(
        public bool $allowed,
        public ApiKeyTier $tier,
        public int $dailyUsed,
        public int $dailyLimit,
        public int $monthlyUsed,
        public int $monthlyLimit,
    ) {}

    public function remainingDaily(): int
    {
        return max(0, $this->dailyLimit - $this->dailyUsed);
    }

    public function remainingMonthly(): int
    {
        return max(0, $this->monthlyLimit - $this->monthlyUsed);
    }

    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'tier' => $this->tier->value,
            'daily_used' => $this->dailyUsed,
            'daily_limit' => $this->dailyLimit,
            'monthly_used' => $this->monthlyUsed,
            'monthly_limit' => $this->monthlyLimit,
            'remaining_daily' => $this->remainingDaily(),
            'remaining_monthly' => $this->remainingMonthly(),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            allowed: (bool) $data['allowed'],
            tier: ApiKeyTier::from($data['tier']),
            dailyUsed: (int) $data['daily_used'],
            dailyLimit: (int) $data['daily_limit'],
            monthlyUsed: (int) $data['monthly_used'],
            monthlyLimit: (int) $data['monthly_limit'],
        );
    }
}
