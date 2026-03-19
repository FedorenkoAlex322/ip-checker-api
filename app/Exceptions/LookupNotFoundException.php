<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;

final class LookupNotFoundException extends ApiException
{
    public function __construct(
        public readonly string $uuid,
        string $message = '',
    ) {
        if ($message === '') {
            $message = "Lookup result not found: {$uuid}";
        }

        parent::__construct(
            message: $message,
            errorCode: ErrorCode::LookupNotFound,
        );
    }
}
