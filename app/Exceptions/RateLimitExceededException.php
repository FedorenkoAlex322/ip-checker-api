<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;
use Illuminate\Http\JsonResponse;

final class RateLimitExceededException extends ApiException
{
    public function __construct(
        int $retryAfter,
        string $message = '',
    ) {
        parent::__construct(
            message: $message,
            errorCode: ErrorCode::RateLimitExceeded,
        );

        $this->retryAfter = $retryAfter;
    }

    public function render(): JsonResponse
    {
        $response = parent::render();
        $response->header('Retry-After', (string) $this->retryAfter);

        return $response;
    }
}
