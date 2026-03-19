<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\QuotaController;
use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\CheckQuota;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\RateLimitByApiKey;
use App\Http\Middleware\RequestLogger;
use Illuminate\Support\Facades\Route;

// Health check — no auth required
Route::middleware([ForceJsonResponse::class])
    ->prefix('v1')
    ->group(function (): void {
        Route::get('/health', [HealthController::class, 'show']);
    });

// Protected API routes
Route::middleware([
    ForceJsonResponse::class,
    AuthenticateApiKey::class,
    CheckQuota::class,
    RateLimitByApiKey::class,
    RequestLogger::class,
])
    ->prefix('v1')
    ->group(function (): void {
        // Lookup endpoints
        Route::post('/lookup/ip', [LookupController::class, 'ip']);
        Route::post('/lookup/domain', [LookupController::class, 'domain']);
        Route::post('/lookup/email', [LookupController::class, 'email']);
        Route::get('/lookup/history', [LookupController::class, 'history']);
        Route::get('/lookup/{uuid}', [LookupController::class, 'show']);

        // Quota
        Route::get('/quota', [QuotaController::class, 'show']);
    });
