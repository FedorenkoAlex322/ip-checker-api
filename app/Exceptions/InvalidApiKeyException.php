<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;

final class InvalidApiKeyException extends ApiException
{
    public function __construct(string $message = 'Invalid or missing API key.')
    {
        parent::__construct(
            message: $message,
            errorCode: ErrorCode::InvalidApiKey,
        );
    }
}
