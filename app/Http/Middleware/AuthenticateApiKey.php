<?php

namespace App\Http\Middleware;

use App\Exceptions\InvalidApiKeyException;
use App\Services\ApiKey\ApiKeyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateApiKey
{
    public function __construct(
        private readonly ApiKeyService $apiKeyService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $apiKeyHeader = $request->header('X-API-Key');

        if ($apiKeyHeader === null || $apiKeyHeader === '') {
            throw new InvalidApiKeyException('API key is required. Pass it via X-API-Key header.');
        }

        $apiKey = $this->apiKeyService->validateKey($apiKeyHeader);

        $request->attributes->set('api_key', $apiKey);
        $request->attributes->set('api_key_id', $apiKey->id);

        return $next($request);
    }
}
