<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ErrorCode;
use Illuminate\Http\JsonResponse;

final class CircuitBreakerOpenException extends ApiException
{
    public function __construct(
        public readonly string $serviceName,
        int $retryAfter,
        string $message = '',
    ) {
        parent::__construct(
            message: $message,
            errorCode: ErrorCode::CircuitBreakerOpen,
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
