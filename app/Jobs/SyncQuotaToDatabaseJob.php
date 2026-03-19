<?php

namespace App\Jobs;

use App\Models\ApiKey;
use App\Models\ApiKeyUsage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

final class SyncQuotaToDatabaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 15;

    public function handle(): void
    {
        $apiKeys = ApiKey::where('is_active', true)->cursor();
        $today = now()->toDateString();
        $synced = 0;
        $totalKeys = 0;

        foreach ($apiKeys as $apiKey) {
            $totalKeys++;
            $redisKey = "quota:{$apiKey->id}:daily:{$today}";
            $count = (int) Redis::get($redisKey);

            if ($count > 0) {
                ApiKeyUsage::updateOrCreate(
                    ['api_key_id' => $apiKey->id, 'date' => $today],
                    ['request_count' => $count],
                );
                $synced++;
            }
        }

        Log::info('Quota synced to database', [
            'total_keys' => $totalKeys,
            'synced' => $synced,
            'date' => $today,
        ]);
    }
}
