<?php

namespace App\Http\Middleware;

use App\Enums\LookupType;
use App\Models\LookupLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequestLogger
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('request_start_time', microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        /** @var float $startTime */
        $startTime = $request->attributes->get('request_start_time', microtime(true));
        $responseTime = (microtime(true) - $startTime) * 1000;

        LookupLog::create([
            'api_key_id' => $request->attributes->get('api_key_id'),
            'method' => $request->method(),
            'endpoint' => $request->path(),
            'target' => $request->input('target'),
            'type' => $this->extractType($request),
            'status_code' => $response->getStatusCode(),
            'response_time_ms' => round($responseTime, 2),
            'ip_address' => $request->ip() ?? '0.0.0.0',
            'user_agent' => $request->userAgent(),
            'error_code' => $response->getStatusCode() >= 400
                ? $this->extractErrorCode($response)
                : null,
        ]);
    }

    /**
     * Derive the LookupType from the matched route name.
     *
     * Route names are expected to follow the pattern "v1.lookup.{type}"
     * where {type} is one of the LookupType enum values (ip, domain, email).
     */
    private function extractType(Request $request): ?LookupType
    {
        $routeName = $request->route()?->getName();

        if ($routeName === null) {
            return null;
        }

        $segments = explode('.', $routeName);
        $typeSegment = end($segments);

        return LookupType::tryFrom($typeSegment);
    }

    /**
     * Extract the application error code string from a JSON error response body.
     *
     * Expects the standard envelope: {"error": {"code": "ERROR_CODE", ...}}
     */
    private function extractErrorCode(Response $response): ?string
    {
        $content = $response->getContent();

        if ($content === false || $content === '') {
            return null;
        }

        /** @var array{error?: array{code?: string}}|null $decoded */
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            return null;
        }

        /** @var string|null $code */
        $code = $decoded['error']['code'] ?? null;

        return $code;
    }
}
