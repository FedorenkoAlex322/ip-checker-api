<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\RateLimiterInterface;
use App\Exceptions\RateLimitExceededException;
use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RateLimitByApiKey
{
    public function __construct(
        private readonly RateLimiterInterface $rateLimiter,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var ApiKey $apiKey */
        $apiKey = $request->attributes->get('api_key');

        $key = 'api_key:'.$apiKey->id;

        $result = $this->rateLimiter->attempt(
            $key,
            $apiKey->rate_limit_per_minute,
            config('rate-limiting.sliding_window_size', 60),
        );

        if (! $result->allowed) {
            throw new RateLimitExceededException($result->retryAfter);
        }

        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', (string) $result->limit);
        $response->headers->set('X-RateLimit-Remaining', (string) $result->remaining);
        $response->headers->set('X-RateLimit-Reset', (string) $result->resetAt);

        return $response;
    }
}
