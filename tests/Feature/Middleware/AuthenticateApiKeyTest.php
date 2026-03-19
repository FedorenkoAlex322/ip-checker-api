<?php

namespace Tests\Feature\Middleware;

use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\MocksRedisServices;

final class AuthenticateApiKeyTest extends TestCase
{
    use MocksRedisServices;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // We mock only Redis services here; API key validation uses the real
        // ApiKeyService + database so we can test the full auth flow.
        $this->setUpApiKeyAndMocks();
    }

    // ------------------------------------------------------------------
    // Valid key
    // ------------------------------------------------------------------

    public function test_valid_api_key_passes(): void
    {
        $response = $this->getJson('/api/v1/quota', $this->apiKeyHeaders());

        $response->assertOk();
    }

    public function test_valid_api_key_updates_last_used_at(): void
    {
        $this->assertNull($this->apiKey->fresh()->last_used_at);

        $this->getJson('/api/v1/quota', $this->apiKeyHeaders());

        $this->assertNotNull($this->apiKey->fresh()->last_used_at);
    }

    // ------------------------------------------------------------------
    // Missing key
    // ------------------------------------------------------------------

    public function test_missing_api_key_returns_401(): void
    {
        $response = $this->getJson('/api/v1/quota');

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'INVALID_API_KEY')
            ->assertJsonPath('error.message', 'API key is required. Pass it via X-API-Key header.');
    }

    public function test_empty_api_key_header_returns_401(): void
    {
        $response = $this->getJson('/api/v1/quota', ['X-API-Key' => '']);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    // Invalid key
    // ------------------------------------------------------------------

    public function test_invalid_api_key_returns_401(): void
    {
        $response = $this->getJson('/api/v1/quota', [
            'X-API-Key' => 'this-key-does-not-exist-in-db',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'INVALID_API_KEY');
    }

    // ------------------------------------------------------------------
    // Expired key
    // ------------------------------------------------------------------

    public function test_expired_api_key_returns_401(): void
    {
        $expiredPlaintext = 'expired-key-'.bin2hex(random_bytes(16));
        ApiKey::factory()->free()->expired()->create([
            'key_hash' => hash('sha256', $expiredPlaintext),
        ]);

        $response = $this->getJson('/api/v1/quota', [
            'X-API-Key' => $expiredPlaintext,
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'INVALID_API_KEY')
            ->assertJsonPath('error.message', 'API key has expired.');
    }

    // ------------------------------------------------------------------
    // Inactive key
    // ------------------------------------------------------------------

    public function test_inactive_api_key_returns_401(): void
    {
        $inactivePlaintext = 'inactive-key-'.bin2hex(random_bytes(16));
        ApiKey::factory()->free()->inactive()->create([
            'key_hash' => hash('sha256', $inactivePlaintext),
        ]);

        $response = $this->getJson('/api/v1/quota', [
            'X-API-Key' => $inactivePlaintext,
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'INVALID_API_KEY')
            ->assertJsonPath('error.message', 'API key is inactive.');
    }
}
