<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ErrorCode;

final class MaxRetriesExceededException extends ApiException
{
    public function __construct(
        public readonly int $attempts,
        string $message = '',
    ) {
        parent::__construct(
            message: $message,
            errorCode: ErrorCode::MaxRetriesExceeded,
        );
    }
}
