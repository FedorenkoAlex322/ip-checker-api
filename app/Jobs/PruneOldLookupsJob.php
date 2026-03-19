<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\LookupLog;
use App\Models\LookupResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class PruneOldLookupsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public function handle(): void
    {
        $retentionDays = (int) config('ip-checker.log_retention_days', 90);
        $cutoff = now()->subDays($retentionDays);

        $deletedLogs = LookupLog::where('created_at', '<', $cutoff)->delete();
        $deletedResults = LookupResult::where('created_at', '<', $cutoff)->delete();

        Log::info('Pruned old lookups', [
            'retention_days' => $retentionDays,
            'cutoff' => $cutoff->toDateTimeString(),
            'deleted_logs' => $deletedLogs,
            'deleted_results' => $deletedResults,
        ]);
    }
}
