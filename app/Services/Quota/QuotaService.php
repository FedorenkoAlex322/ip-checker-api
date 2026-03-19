<?php

namespace App\Services\Quota;

use App\Contracts\QuotaServiceInterface;
use App\DTOs\QuotaStatus;
use App\DTOs\QuotaUsage;
use App\Enums\ApiKeyTier;
use App\Models\ApiKey;
use App\Models\ApiKeyUsage;
use Illuminate\Support\Facades\Redis;

final class QuotaService implements QuotaServiceInterface
{
    private const int DAILY_TTL = 86400 + 3600;   // 25 hours buffer

    private const int MONTHLY_TTL = 86400 * 35;   // 35 days buffer

    public function check(int $apiKeyId): QuotaStatus
    {
        /** @var ApiKey $apiKey */
        $apiKey = ApiKey::findOrFail($apiKeyId);

        $dailyUsed = $this->getDailyCount($apiKeyId);
        $monthlyUsed = $this->getMonthlyCount($apiKeyId);

        $allowed = $dailyUsed < $apiKey->daily_limit
                && $monthlyUsed < $apiKey->monthly_limit;

        /** @var ApiKeyTier $tier */
        $tier = $apiKey->tier;

        return new QuotaStatus(
            allowed: $allowed,
            tier: $tier,
            dailyUsed: $dailyUsed,
            dailyLimit: $apiKey->daily_limit,
            monthlyUsed: $monthlyUsed,
            monthlyLimit: $apiKey->monthly_limit,
        );
    }

    public function increment(int $apiKeyId): void
    {
        $dailyKey = $this->getDailyKey($apiKeyId);
        $monthlyKey = $this->getMonthlyKey($apiKeyId);

        Redis::incr($dailyKey);
        Redis::expire($dailyKey, self::DAILY_TTL);
        Redis::incr($monthlyKey);
        Redis::expire($monthlyKey, self::MONTHLY_TTL);
    }

    public function getUsage(int $apiKeyId): QuotaUsage
    {
        return new QuotaUsage(
            apiKeyId: $apiKeyId,
            date: now()->toDateString(),
            requestCount: $this->getDailyCount($apiKeyId),
        );
    }

    private function getDailyCount(int $apiKeyId): int
    {
        $key = $this->getDailyKey($apiKeyId);
        $count = Redis::get($key);

        if ($count === null) {
            $count = $this->loadDailyFromDatabase($apiKeyId, now()->toDateString());

            if ($count > 0) {
                Redis::setex($key, self::DAILY_TTL, (string) $count);
            }
        }

        return (int) $count;
    }

    private function getMonthlyCount(int $apiKeyId): int
    {
        $key = $this->getMonthlyKey($apiKeyId);
        $count = Redis::get($key);

        if ($count === null) {
            $count = $this->loadMonthlyFromDatabase($apiKeyId);

            if ($count > 0) {
                Redis::setex($key, self::MONTHLY_TTL, (string) $count);
            }
        }

        return (int) $count;
    }

    private function loadDailyFromDatabase(int $apiKeyId, string $date): int
    {
        $usage = ApiKeyUsage::where('api_key_id', $apiKeyId)
            ->where('date', $date)
            ->first();

        if ($usage === null) {
            return 0;
        }

        return $usage->request_count;
    }

    private function loadMonthlyFromDatabase(int $apiKeyId): int
    {
        $startOfMonth = now()->startOfMonth()->toDateString();
        $today = now()->toDateString();

        return (int) ApiKeyUsage::where('api_key_id', $apiKeyId)
            ->whereBetween('date', [$startOfMonth, $today])
            ->sum('request_count');
    }

    private function getDailyKey(int $apiKeyId): string
    {
        return "quota:{$apiKeyId}:daily:".now()->format('Y-m-d');
    }

    private function getMonthlyKey(int $apiKeyId): string
    {
        return "quota:{$apiKeyId}:monthly:".now()->format('Y-m');
    }
}
