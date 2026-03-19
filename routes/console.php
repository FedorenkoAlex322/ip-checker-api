<?php

use App\Jobs\PruneOldLookupsJob;
use App\Jobs\SyncQuotaToDatabaseJob;
use Illuminate\Support\Facades\Schedule;

// Sync Redis quota counters to MySQL every 5 minutes
Schedule::job(new SyncQuotaToDatabaseJob)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Prune old lookup logs and results daily at 3 AM
Schedule::job(new PruneOldLookupsJob)
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onOneServer();
