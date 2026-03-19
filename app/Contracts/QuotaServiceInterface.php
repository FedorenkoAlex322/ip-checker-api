<?php

namespace App\Contracts;

use App\DTOs\QuotaStatus;
use App\DTOs\QuotaUsage;

interface QuotaServiceInterface
{
    public function check(int $apiKeyId): QuotaStatus;

    public function increment(int $apiKeyId): void;

    public function getUsage(int $apiKeyId): QuotaUsage;
}
