<?php

namespace App\Services\Retry;

use App\Contracts\RetryableInterface;
use App\DTOs\RetryConfig;
use App\Exceptions\MaxRetriesExceededException;
use Illuminate\Support\Facades\Log;

final class RetryService implements RetryableInterface
{
    public function execute(callable $operation, RetryConfig $config): mixed
    {
        $lastException = null;

        for ($attempt = 0; $attempt <= $config->maxRetries; $attempt++) {
            try {
                return $operation();
            } catch (\Throwable $e) {
                $lastException = $e;

                if (! $this->isRetryable($e, $config)) {
                    throw $e;
                }

                if ($attempt === $config->maxRetries) {
                    break;
                }

                $delay = $this->calculateDelay($attempt, $config);

                Log::warning('Retry attempt', [
                    'attempt' => $attempt + 1,
                    'max_retries' => $config->maxRetries,
                    'delay_ms' => $delay,
                    'exception' => $e->getMessage(),
                ]);

                usleep($delay * 1000);
            }
        }

        throw new MaxRetriesExceededException(
            attempts: $config->maxRetries + 1,
            message: sprintf(
                'Operation failed after %d attempts: %s',
                $config->maxRetries + 1,
                $lastException?->getMessage() ?? 'Unknown error',
            ),
        );
    }

    private function calculateDelay(int $attempt, RetryConfig $config): int
    {
        $delay = (int) ($config->baseDelayMs * ($config->multiplier ** $attempt));

        $delay = min($delay, $config->maxDelayMs);

        if ($config->jitterEnabled) {
            $jitter = (int) ($delay * 0.25);

            if ($jitter > 0) {
                $delay += random_int(-$jitter, $jitter);
            }

            $delay = max(0, $delay);
        }

        return $delay;
    }

    private function isRetryable(\Throwable $e, RetryConfig $config): bool
    {
        if ($config->retryableExceptions === []) {
            return true;
        }

        foreach ($config->retryableExceptions as $retryableClass) {
            if ($e instanceof $retryableClass) {
                return true;
            }
        }

        return false;
    }
}
