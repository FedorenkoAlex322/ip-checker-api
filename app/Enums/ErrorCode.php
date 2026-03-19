<?php

namespace App\Enums;

enum ErrorCode: string
{
    case RateLimitExceeded = 'RATE_LIMIT_EXCEEDED';
    case QuotaExceeded = 'QUOTA_EXCEEDED';
    case InvalidApiKey = 'INVALID_API_KEY';
    case ProviderUnavailable = 'PROVIDER_UNAVAILABLE';
    case CircuitBreakerOpen = 'CIRCUIT_BREAKER_OPEN';
    case MaxRetriesExceeded = 'MAX_RETRIES_EXCEEDED';
    case LookupNotFound = 'LOOKUP_NOT_FOUND';
    case ValidationError = 'VALIDATION_ERROR';
    case InternalError = 'INTERNAL_ERROR';

    public function httpStatus(): int
    {
        return match ($this) {
            self::RateLimitExceeded => 429,
            self::QuotaExceeded => 429,
            self::InvalidApiKey => 401,
            self::ProviderUnavailable => 503,
            self::CircuitBreakerOpen => 503,
            self::MaxRetriesExceeded => 502,
            self::LookupNotFound => 404,
            self::ValidationError => 422,
            self::InternalError => 500,
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::RateLimitExceeded => 'Rate limit exceeded. Please try again later.',
            self::QuotaExceeded => 'API quota exceeded. Please upgrade your plan or wait for quota reset.',
            self::InvalidApiKey => 'Invalid or missing API key.',
            self::ProviderUnavailable => 'External provider is temporarily unavailable.',
            self::CircuitBreakerOpen => 'Service is temporarily unavailable due to repeated failures.',
            self::MaxRetriesExceeded => 'Maximum retry attempts exceeded. Please try again later.',
            self::LookupNotFound => 'The requested lookup was not found.',
            self::ValidationError => 'Validation failed. Please check the request parameters.',
            self::InternalError => 'An internal server error occurred.',
        };
    }
}
