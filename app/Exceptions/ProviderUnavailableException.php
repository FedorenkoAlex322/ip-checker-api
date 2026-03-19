<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;

final class ProviderUnavailableException extends ApiException
{
    public function __construct(
        public readonly string $providerName,
        string $message = '',
    ) {
        parent::__construct(
            message: $message,
            errorCode: ErrorCode::ProviderUnavailable,
        );
    }
}
