<?php

namespace Database\Factories;

use App\Models\ApiKey;
use App\Models\ApiKeyUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiKeyUsage>
 */
class ApiKeyUsageFactory extends Factory
{
    protected $model = ApiKeyUsage::class;

    public function definition(): array
    {
        return [
            'api_key_id' => ApiKey::factory(),
            'date' => now()->toDateString(),
            'request_count' => $this->faker->numberBetween(1, 100),
        ];
    }
}
