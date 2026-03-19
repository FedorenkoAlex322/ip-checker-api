<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTOs\RetryConfig;
use App\Exceptions\MaxRetriesExceededException;
use App\Services\Retry\RetryService;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RetryServiceTest extends TestCase
{
    private RetryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new RetryService;
    }

    #[Test]
    public function test_executes_operation_successfully_on_first_try(): void
    {
        // Arrange
        $config = new RetryConfig(
            maxRetries: 3,
            baseDelayMs: 10,
            jitterEnabled: false,
        );

        $callCount = 0;
        $operation = function () use (&$callCount): string {
            $callCount++;

            return 'success';
        };

        // Act
        $result = $this->service->execute($operation, $config);

        // Assert
        $this->assertSame('success', $result);
        $this->assertSame(1, $callCount);
    }

    #[Test]
    public function test_retries_on_transient_failure_and_succeeds(): void
    {
        // Arrange
        $config = new RetryConfig(
            maxRetries: 3,
            baseDelayMs: 1,
            maxDelayMs: 10,
            jitterEnabled: false,
            retryableExceptions: [\RuntimeException::class],
        );

        Log::shouldReceive('warning')->twice();

        $callCount = 0;
        $operation = function () use (&$callCount): string {
            $callCount++;
            if ($callCount < 3) {
                throw new \RuntimeException('transient error');
            }

            return 'recovered';
        };

        // Act
        $result = $this->service->execute($operation, $config);

        // Assert
        $this->assertSame('recovered', $result);
        $this->assertSame(3, $callCount);
    }

    #[Test]
    public function test_throws_max_retries_exceeded_after_all_attempts(): void
    {
        // Arrange
        $config = new RetryConfig(
            maxRetries: 2,
            baseDelayMs: 1,
            maxDelayMs: 10,
            jitterEnabled: false,
            retryableExceptions: [\RuntimeException::class],
        );

        Log::shouldReceive('warning')->twice();

        $callCount = 0;
        $operation = function () use (&$callCount): never {
            $callCount++;
            throw new \RuntimeException('always fails');
        };

        // Act & Assert
        $this->expectException(MaxRetriesExceededException::class);
        $this->expectExceptionMessageMatches('/failed after 3 attempts/');

        try {
            $this->service->execute($operation, $config);
        } finally {
            $this->assertSame(3, $callCount);
        }
    }

    #[Test]
    public function test_does_not_retry_non_retryable_exceptions(): void
    {
        // Arrange
        $config = new RetryConfig(
            maxRetries: 3,
            baseDelayMs: 1,
            jitterEnabled: false,
            retryableExceptions: [\RuntimeException::class],
        );

        $callCount = 0;
        $operation = function () use (&$callCount): never {
            $callCount++;
            throw new \InvalidArgumentException('non-retryable');
        };

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('non-retryable');

        try {
            $this->service->execute($operation, $config);
        } finally {
            $this->assertSame(1, $callCount);
        }
    }

    #[Test]
    public function test_exponential_backoff_delay_calculation(): void
    {
        // Arrange
        $config = new RetryConfig(
            maxRetries: 3,
            baseDelayMs: 100,
            maxDelayMs: 10000,
            multiplier: 2.0,
            jitterEnabled: false,
            retryableExceptions: [\RuntimeException::class],
        );

        $timestamps = [];
        $callCount = 0;

        Log::shouldReceive('warning')->times(3);

        $operation = function () use (&$callCount, &$timestamps): never {
            $timestamps[] = microtime(true);
            $callCount++;
            throw new \RuntimeException('fail');
        };

        // Act
        try {
            $this->service->execute($operation, $config);
        } catch (MaxRetriesExceededException) {
            // expected
        }

        // Assert
        $this->assertSame(4, $callCount);

        // Check that delays increase exponentially:
        // attempt 0: 100ms, attempt 1: 200ms, attempt 2: 400ms
        if (count($timestamps) >= 3) {
            $delay1Ms = ($timestamps[1] - $timestamps[0]) * 1000;
            $delay2Ms = ($timestamps[2] - $timestamps[1]) * 1000;

            // delay2 should be roughly 2x delay1 (with tolerance for execution overhead)
            $this->assertGreaterThan(50, $delay1Ms, 'First delay should be at least 50ms');
            $this->assertGreaterThan($delay1Ms * 1.3, $delay2Ms, 'Second delay should be significantly larger than first');
        }
    }

    #[Test]
    public function test_respects_max_delay_cap(): void
    {
        // Arrange
        $config = new RetryConfig(
            maxRetries: 5,
            baseDelayMs: 1000,
            maxDelayMs: 50, // cap is less than base - delay should be capped
            multiplier: 10.0,
            jitterEnabled: false,
            retryableExceptions: [\RuntimeException::class],
        );

        $timestamps = [];

        Log::shouldReceive('warning')->times(5);

        $operation = function () use (&$timestamps): never {
            $timestamps[] = microtime(true);
            throw new \RuntimeException('fail');
        };

        // Act
        try {
            $this->service->execute($operation, $config);
        } catch (MaxRetriesExceededException) {
            // expected
        }

        // Assert - each delay should be capped at maxDelayMs (50ms)
        for ($i = 1; $i < count($timestamps); $i++) {
            $delayMs = ($timestamps[$i] - $timestamps[$i - 1]) * 1000;
            // Allow some tolerance for execution overhead but should not exceed cap + tolerance
            $this->assertLessThan(150, $delayMs, "Delay at attempt {$i} should be capped near 50ms");
        }
    }
}
