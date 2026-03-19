<?php

declare(strict_types=1);

namespace App\Services\ApiKey;

use App\Enums\ApiKeyTier;
use App\Exceptions\InvalidApiKeyException;
use App\Models\ApiKey;

final class ApiKeyService
{
    /**
     * Generate a new API key. Returns the plaintext key (shown to the user only once).
     */
    public function generateKey(string $name, ApiKeyTier $tier = ApiKeyTier::Free): string
    {
        $plaintext = $this->generateRandomKey();
        $hash = $this->hashKey($plaintext);

        $limits = $this->getLimitsForTier($tier);

        ApiKey::create([
            'key_hash' => $hash,
            'name' => $name,
            'tier' => $tier,
            'daily_limit' => $limits['daily_limit'],
            'monthly_limit' => $limits['monthly_limit'],
            'rate_limit_per_minute' => $limits['requests_per_minute'],
        ]);

        return $plaintext;
    }

    /**
     * Validate an API key from the request header. Returns the ApiKey model.
     *
     * @throws InvalidApiKeyException
     */
    public function validateKey(string $plaintext): ApiKey
    {
        $hash = $this->hashKey($plaintext);

        $apiKey = ApiKey::where('key_hash', $hash)->first();

        if ($apiKey === null) {
            throw new InvalidApiKeyException();
        }

        if (! $apiKey->isUsable()) {
            throw new InvalidApiKeyException(
                $apiKey->isExpired()
                    ? 'API key has expired.'
                    : 'API key is inactive.'
            );
        }

        $apiKey->updateQuietly(['last_used_at' => now()]);

        return $apiKey;
    }

    /**
     * Deactivate an API key (soft disable).
     */
    public function deactivateKey(int $id): bool
    {
        $apiKey = ApiKey::findOrFail($id);

        return $apiKey->update(['is_active' => false]);
    }

    /**
     * Generate a cryptographically secure random key (32 bytes -> base64url, 43 chars).
     */
    private function generateRandomKey(): string
    {
        $bytes = random_bytes(32);

        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /**
     * Hash a plaintext key using SHA-256.
     */
    private function hashKey(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }

    /**
     * Retrieve rate/quota limits for the given tier from config.
     *
     * @return array{requests_per_minute: int, daily_limit: int, monthly_limit: int}
     */
    private function getLimitsForTier(ApiKeyTier $tier): array
    {
        /** @var array{requests_per_minute: int, daily_limit: int, monthly_limit: int} $limits */
        $limits = config("rate-limiting.tiers.{$tier->value}", [
            'requests_per_minute' => 60,
            'daily_limit' => 1000,
            'monthly_limit' => 10000,
        ]);

        return $limits;
    }
}
