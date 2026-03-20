<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;

final class ProviderException extends ApiException
{
    public function __construct(
        public readonly string $providerName,
        string $message = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            message: "Provider [{$providerName}]: {$message}",
            errorCode: ErrorCode::ProviderError,
            previous: $previous,
        );
    }
}
