<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\QuotaServiceInterface;
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
            throw new QuotaExceededException($quotaType);
        }

        return $next($request);
    }
}
