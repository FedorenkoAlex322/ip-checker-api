<?php

namespace App\Http\Resources;

use App\DTOs\QuotaStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class QuotaResource extends JsonResource
{
    public function __construct(
        private readonly QuotaStatus $quota,
    ) {
        parent::__construct($quota);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'data' => [
                'tier' => $this->quota->tier->value,
                'daily' => [
                    'used' => $this->quota->dailyUsed,
                    'limit' => $this->quota->dailyLimit,
                    'remaining' => $this->quota->remainingDaily(),
                ],
                'monthly' => [
                    'used' => $this->quota->monthlyUsed,
                    'limit' => $this->quota->monthlyLimit,
                    'remaining' => $this->quota->remainingMonthly(),
                ],
            ],
        ];
    }
}
