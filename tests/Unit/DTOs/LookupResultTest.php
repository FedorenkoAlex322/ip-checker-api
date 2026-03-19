<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\LookupResult;
use App\Enums\LookupType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LookupResultTest extends TestCase
{
    #[Test]
    public function test_to_array_returns_correct_structure(): void
    {
        // Arrange
        $dto = new LookupResult(
            uuid: 'abc-123',
            target: '8.8.8.8',
            type: LookupType::Ip,
            provider: 'mock',
            resultData: ['ip' => '8.8.8.8', 'risk_score' => 42],
            lookupTimeMs: 150.5,
            cached: true,
        );

        // Act
        $array = $dto->toArray();

        // Assert
        $this->assertSame([
            'uuid' => 'abc-123',
            'target' => '8.8.8.8',
            'type' => 'ip',
            'provider' => 'mock',
            'result_data' => ['ip' => '8.8.8.8', 'risk_score' => 42],
            'lookup_time_ms' => 150.5,
            'cached' => true,
        ], $array);
    }

    #[Test]
    public function test_from_array_creates_correct_dto(): void
    {
        // Arrange
        $data = [
            'uuid' => 'xyz-789',
            'target' => 'example.com',
            'type' => 'domain',
            'provider' => 'test-provider',
            'result_data' => ['domain' => 'example.com', 'risk_score' => 15],
            'lookup_time_ms' => 200.0,
            'cached' => false,
        ];

        // Act
        $dto = LookupResult::fromArray($data);

        // Assert
        $this->assertSame('xyz-789', $dto->uuid);
        $this->assertSame('example.com', $dto->target);
        $this->assertSame(LookupType::Domain, $dto->type);
        $this->assertSame('test-provider', $dto->provider);
        $this->assertSame(['domain' => 'example.com', 'risk_score' => 15], $dto->resultData);
        $this->assertSame(200.0, $dto->lookupTimeMs);
        $this->assertFalse($dto->cached);
    }

    #[Test]
    public function test_roundtrip_serialization(): void
    {
        // Arrange
        $original = new LookupResult(
            uuid: 'roundtrip-uuid',
            target: 'user@test.com',
            type: LookupType::Email,
            provider: 'email-checker',
            resultData: [
                'email' => 'user@test.com',
                'risk_score' => 30,
                'is_disposable' => false,
                'nested' => ['key' => 'value'],
            ],
            lookupTimeMs: 99.9,
            cached: true,
        );

        // Act
        $array = $original->toArray();
        $restored = LookupResult::fromArray($array);

        // Assert
        $this->assertSame($original->uuid, $restored->uuid);
        $this->assertSame($original->target, $restored->target);
        $this->assertSame($original->type, $restored->type);
        $this->assertSame($original->provider, $restored->provider);
        $this->assertSame($original->resultData, $restored->resultData);
        $this->assertSame($original->lookupTimeMs, $restored->lookupTimeMs);
        $this->assertSame($original->cached, $restored->cached);
    }
}
