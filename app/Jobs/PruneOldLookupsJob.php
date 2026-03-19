<?php

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

        $deletedLogs = 0;
        LookupLog::where('created_at', '<', $cutoff)
            ->chunkById(1000, function ($logs) use (&$deletedLogs) {
                $deletedLogs += $logs->count();
                LookupLog::whereIn('id', $logs->pluck('id'))->delete();
            });

        $deletedResults = 0;
        LookupResult::where('created_at', '<', $cutoff)
            ->chunkById(1000, function ($results) use (&$deletedResults) {
                $deletedResults += $results->count();
                LookupResult::whereIn('id', $results->pluck('id'))->delete();
            });

        Log::info('Pruned old lookups', [
            'retention_days' => $retentionDays,
            'cutoff' => $cutoff->toDateTimeString(),
            'deleted_logs' => $deletedLogs,
            'deleted_results' => $deletedResults,
        ]);
    }
}
