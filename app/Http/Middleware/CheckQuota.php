<?php

namespace App\Http\Middleware;

use App\Contracts\QuotaServiceInterface;
use App\Events\QuotaExceeded;
use App\Exceptions\QuotaExceededException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CheckQuota
{
    public function __construct(
        private readonly QuotaServiceInterface $quotaService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var int $apiKeyId */
        $apiKeyId = $request->attributes->get('api_key_id');

        $quotaStatus = $this->quotaService->check($apiKeyId);

        if (! $quotaStatus->allowed) {
            $quotaType = $quotaStatus->remainingDaily() <= 0 ? 'daily' : 'monthly';
            $currentUsage = $quotaType === 'daily' ? $quotaStatus->dailyUsed : $quotaStatus->monthlyUsed;
            $limit = $quotaType === 'daily' ? $quotaStatus->dailyLimit : $quotaStatus->monthlyLimit;

            QuotaExceeded::dispatch($apiKeyId, $quotaType, $currentUsage, $limit);

            throw new QuotaExceededException($quotaType);
        }

        return $next($request);
    }
}
