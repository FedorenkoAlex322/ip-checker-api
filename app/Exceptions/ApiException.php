<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ErrorCode;
use Illuminate\Http\JsonResponse;

abstract class ApiException extends \RuntimeException
{
    protected ErrorCode $errorCode;
    protected int $httpStatusCode;
    protected ?int $retryAfter = null;

    public function __construct(
        string $message = '',
        ?ErrorCode $errorCode = null,
        ?int $httpStatusCode = null,
        ?\Throwable $previous = null,
    ) {
        $this->errorCode = $errorCode ?? ErrorCode::InternalError;
        $this->httpStatusCode = $httpStatusCode ?? $this->errorCode->httpStatus();

        if ($message === '') {
            $message = $this->errorCode->message();
        }

        parent::__construct($message, 0, $previous);
    }

    public function getErrorCode(): ErrorCode
    {
        return $this->errorCode;
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    public function render(): JsonResponse
    {
        $error = [
            'code' => $this->errorCode->value,
            'message' => $this->getMessage(),
        ];

        if ($this->retryAfter !== null) {
            $error['retry_after'] = $this->retryAfter;
        }

        return new JsonResponse(
            data: ['error' => $error],
            status: $this->httpStatusCode,
        );
    }
}
