<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ApiKeyTier;
use App\Models\ApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiKey>
 */
class ApiKeyFactory extends Factory
{
    protected $model = ApiKey::class;

    private const TIER_DEFAULTS = [
        'free' => [
            'daily_limit' => 1000,
            'monthly_limit' => 10000,
            'rate_limit_per_minute' => 60,
        ],
        'pro' => [
            'daily_limit' => 50000,
            'monthly_limit' => 1000000,
            'rate_limit_per_minute' => 300,
        ],
        'enterprise' => [
            'daily_limit' => 500000,
            'monthly_limit' => 10000000,
            'rate_limit_per_minute' => 1000,
        ],
    ];

    public function definition(): array
    {
        $tier = $this->faker->randomElement(ApiKeyTier::cases());
        $defaults = self::TIER_DEFAULTS[$tier->value];

        return [
            'key_hash' => hash('sha256', Str::random(40)),
            'name' => $this->faker->company() . ' API Key',
            'tier' => $tier,
            'daily_limit' => $defaults['daily_limit'],
            'monthly_limit' => $defaults['monthly_limit'],
            'rate_limit_per_minute' => $defaults['rate_limit_per_minute'],
            'is_active' => true,
            'last_used_at' => null,
            'expires_at' => null,
        ];
    }

    public function free(): static
    {
        return $this->state(fn () => [
            'tier' => ApiKeyTier::Free,
            'daily_limit' => self::TIER_DEFAULTS['free']['daily_limit'],
            'monthly_limit' => self::TIER_DEFAULTS['free']['monthly_limit'],
            'rate_limit_per_minute' => self::TIER_DEFAULTS['free']['rate_limit_per_minute'],
        ]);
    }

    public function pro(): static
    {
        return $this->state(fn () => [
            'tier' => ApiKeyTier::Pro,
            'daily_limit' => self::TIER_DEFAULTS['pro']['daily_limit'],
            'monthly_limit' => self::TIER_DEFAULTS['pro']['monthly_limit'],
            'rate_limit_per_minute' => self::TIER_DEFAULTS['pro']['rate_limit_per_minute'],
        ]);
    }

    public function enterprise(): static
    {
        return $this->state(fn () => [
            'tier' => ApiKeyTier::Enterprise,
            'daily_limit' => self::TIER_DEFAULTS['enterprise']['daily_limit'],
            'monthly_limit' => self::TIER_DEFAULTS['enterprise']['monthly_limit'],
            'rate_limit_per_minute' => self::TIER_DEFAULTS['enterprise']['rate_limit_per_minute'],
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
