<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;

final class QuotaExceededException extends ApiException
{
    public function __construct(
        public readonly string $quotaType = 'daily',
        string $message = '',
    ) {
        parent::__construct(
            message: $message,
            errorCode: ErrorCode::QuotaExceeded,
        );
    }
}
