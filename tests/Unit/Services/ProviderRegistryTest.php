<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\CircuitBreakerInterface;
use App\Contracts\LookupProviderInterface;
use App\Enums\LookupType;
use App\Exceptions\ProviderUnavailableException;
use App\Services\Providers\ProviderRegistry;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProviderRegistryTest extends TestCase
{
    private CircuitBreakerInterface&MockInterface $circuitBreaker;

    private ProviderRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $this->registry = new ProviderRegistry($this->circuitBreaker);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function test_registers_and_retrieves_provider(): void
    {
        // Arrange
        $provider = $this->createMockProvider('test-provider', 10, [LookupType::Ip]);

        $this->circuitBreaker
            ->shouldReceive('isAvailable')
            ->with('test-provider')
            ->andReturn(true);

        $this->registry->register($provider);

        // Act
        $result = $this->registry->getProvider(LookupType::Ip);

        // Assert
        $this->assertSame($provider, $result);
    }

    #[Test]
    public function test_returns_highest_priority_provider(): void
    {
        // Arrange - lower priority value = higher priority
        $lowPriority = $this->createMockProvider('low', 100, [LookupType::Ip]);
        $highPriority = $this->createMockProvider('high', 1, [LookupType::Ip]);

        $this->circuitBreaker
            ->shouldReceive('isAvailable')
            ->andReturn(true);

        $this->registry->register($lowPriority);
        $this->registry->register($highPriority);

        // Act
        $result = $this->registry->getProvider(LookupType::Ip);

        // Assert
        $this->assertSame('high', $result->getName());
    }

    #[Test]
    public function test_skips_unavailable_providers(): void
    {
        // Arrange
        $unavailable = $this->createMockProvider('unavailable', 1, [LookupType::Ip]);
        $available = $this->createMockProvider('available', 50, [LookupType::Ip]);

        $this->circuitBreaker
            ->shouldReceive('isAvailable')
            ->with('unavailable')
            ->andReturn(false);

        $this->circuitBreaker
            ->shouldReceive('isAvailable')
            ->with('available')
            ->andReturn(true);

        $this->registry->register($unavailable);
        $this->registry->register($available);

        // Act
        $result = $this->registry->getProvider(LookupType::Ip);

        // Assert
        $this->assertSame('available', $result->getName());
    }

    #[Test]
    public function test_throws_when_no_providers_available(): void
    {
        // Arrange
        $provider = $this->createMockProvider('down', 1, [LookupType::Ip]);

        $this->circuitBreaker
            ->shouldReceive('isAvailable')
            ->with('down')
            ->andReturn(false);

        $this->registry->register($provider);

        // Act & Assert
        $this->expectException(ProviderUnavailableException::class);

        $this->registry->getProvider(LookupType::Ip);
    }

    #[Test]
    public function test_supports_preferred_provider(): void
    {
        // Arrange
        $primary = $this->createMockProvider('primary', 1, [LookupType::Ip]);
        $preferred = $this->createMockProvider('preferred', 100, [LookupType::Ip]);

        $this->circuitBreaker
            ->shouldReceive('isAvailable')
            ->andReturn(true);

        $this->registry->register($primary);
        $this->registry->register($preferred);

        // Act
        $result = $this->registry->getProvider(LookupType::Ip, 'preferred');

        // Assert
        $this->assertSame('preferred', $result->getName());
    }

    /**
     * @param  array<LookupType>  $supportedTypes
     */
    private function createMockProvider(
        string $name,
        int $priority,
        array $supportedTypes,
        bool $enabled = true,
    ): LookupProviderInterface&MockInterface {
        $provider = Mockery::mock(LookupProviderInterface::class);

        $provider->shouldReceive('getName')->andReturn($name);
        $provider->shouldReceive('getPriority')->andReturn($priority);
        $provider->shouldReceive('isEnabled')->andReturn($enabled);
        $provider->shouldReceive('supports')->andReturnUsing(
            fn (LookupType $type): bool => in_array($type, $supportedTypes, true),
        );

        return $provider;
    }
}
