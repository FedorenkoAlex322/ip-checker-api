<?php

namespace Tests\Feature\Api\V1;

use App\Models\ApiKey;
use App\Models\LookupResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\MocksRedisServices;

final class LookupHistoryTest extends TestCase
{
    use MocksRedisServices;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpApiKeyAndMocks();
    }

    // ------------------------------------------------------------------
    // History list
    // ------------------------------------------------------------------

    public function test_returns_paginated_history(): void
    {
        LookupResult::factory()
            ->count(5)
            ->ip()
            ->create(['api_key_id' => $this->apiKey->id]);

        $response = $this->getJson('/api/v1/lookup/history', $this->apiKeyHeaders());

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page', 'from', 'to'],
                'links' => ['first', 'last', 'prev', 'next'],
            ])
            ->assertJsonPath('meta.total', 5);
    }

    public function test_returns_empty_history(): void
    {
        $response = $this->getJson('/api/v1/lookup/history', $this->apiKeyHeaders());

        $response->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('data', []);
    }

    public function test_history_respects_per_page_parameter(): void
    {
        LookupResult::factory()
            ->count(10)
            ->ip()
            ->create(['api_key_id' => $this->apiKey->id]);

        $response = $this->getJson('/api/v1/lookup/history?per_page=3', $this->apiKeyHeaders());

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 3)
            ->assertJsonPath('meta.total', 10)
            ->assertJsonCount(3, 'data');
    }

    public function test_history_caps_per_page_at_100(): void
    {
        LookupResult::factory()
            ->count(3)
            ->ip()
            ->create(['api_key_id' => $this->apiKey->id]);

        $response = $this->getJson('/api/v1/lookup/history?per_page=500', $this->apiKeyHeaders());

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_history_does_not_include_other_api_key_results(): void
    {
        // Results for our key
        LookupResult::factory()
            ->count(2)
            ->ip()
            ->create(['api_key_id' => $this->apiKey->id]);

        // Results for another key
        $otherKey = ApiKey::factory()->free()->create();
        LookupResult::factory()
            ->count(3)
            ->ip()
            ->create(['api_key_id' => $otherKey->id]);

        $response = $this->getJson('/api/v1/lookup/history', $this->apiKeyHeaders());

        $response->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    // ------------------------------------------------------------------
    // Show by UUID
    // ------------------------------------------------------------------

    public function test_show_by_uuid(): void
    {
        $result = LookupResult::factory()
            ->ip()
            ->create(['api_key_id' => $this->apiKey->id]);

        $response = $this->getJson("/api/v1/lookup/{$result->uuid}", $this->apiKeyHeaders());

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['uuid', 'target', 'type', 'result', 'cached'],
                'meta' => ['provider', 'lookup_time_ms', 'cached'],
            ])
            ->assertJsonPath('data.uuid', $result->uuid);
    }

    public function test_returns_404_for_nonexistent_uuid(): void
    {
        $response = $this->getJson(
            '/api/v1/lookup/00000000-0000-0000-0000-000000000000',
            $this->apiKeyHeaders()
        );

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'LOOKUP_NOT_FOUND');
    }

    // ------------------------------------------------------------------
    // Auth
    // ------------------------------------------------------------------

    public function test_history_returns_401_without_api_key(): void
    {
        $response = $this->getJson('/api/v1/lookup/history');

        $response->assertStatus(401);
    }

    public function test_show_returns_401_without_api_key(): void
    {
        $response = $this->getJson('/api/v1/lookup/some-uuid');

        $response->assertStatus(401);
    }
}
