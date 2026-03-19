<?php

namespace Tests\Unit\Services;

use App\Enums\ApiKeyTier;
use App\Exceptions\InvalidApiKeyException;
use App\Models\ApiKey;
use App\Services\ApiKey\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ApiKeyServiceTest extends TestCase
{
    use RefreshDatabase;

    private ApiKeyService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ApiKeyService;
    }

    #[Test]
    public function test_generates_key_with_correct_format(): void
    {
        // Act
        $key = $this->service->generateKey('Test Key');

        // Assert - 32 bytes base64url encoded = 43 characters
        $this->assertSame(43, strlen($key));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $key);
    }

    #[Test]
    public function test_validates_correct_key(): void
    {
        // Arrange
        $plaintext = $this->service->generateKey('Valid Key', ApiKeyTier::Pro);

        // Act
        $apiKey = $this->service->validateKey($plaintext);

        // Assert
        $this->assertInstanceOf(ApiKey::class, $apiKey);
        $this->assertSame('Valid Key', $apiKey->name);
        $this->assertSame(ApiKeyTier::Pro, $apiKey->tier);
        $this->assertNotNull($apiKey->last_used_at);
    }

    #[Test]
    public function test_throws_on_invalid_key(): void
    {
        // Arrange
        $invalidKey = str_repeat('a', 43);

        // Act & Assert
        $this->expectException(InvalidApiKeyException::class);
        $this->expectExceptionMessage('Invalid or missing API key.');

        $this->service->validateKey($invalidKey);
    }

    #[Test]
    public function test_throws_on_expired_key(): void
    {
        // Arrange
        $plaintext = $this->service->generateKey('Expired Key');
        $hash = hash('sha256', $plaintext);

        ApiKey::where('key_hash', $hash)->update([
            'expires_at' => now()->subDay(),
        ]);

        // Act & Assert
        $this->expectException(InvalidApiKeyException::class);
        $this->expectExceptionMessage('API key has expired.');

        $this->service->validateKey($plaintext);
    }

    #[Test]
    public function test_throws_on_inactive_key(): void
    {
        // Arrange
        $plaintext = $this->service->generateKey('Inactive Key');
        $hash = hash('sha256', $plaintext);

        ApiKey::where('key_hash', $hash)->update([
            'is_active' => false,
        ]);

        // Act & Assert
        $this->expectException(InvalidApiKeyException::class);
        $this->expectExceptionMessage('API key is inactive.');

        $this->service->validateKey($plaintext);
    }
}
