<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LookupType;
use App\Models\ApiKey;
use App\Models\LookupResult;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LookupResult>
 */
class LookupResultFactory extends Factory
{
    protected $model = LookupResult::class;

    public function definition(): array
    {
        return [
            'uuid' => Str::uuid()->toString(),
            'api_key_id' => ApiKey::factory(),
            'target' => $this->faker->ipv4(),
            'type' => LookupType::Ip,
            'provider' => 'mock',
            'result_data' => $this->ipResultData(),
            'lookup_time_ms' => $this->faker->randomFloat(2, 10, 500),
            'cached' => false,
        ];
    }

    public function ip(): static
    {
        return $this->state(fn () => [
            'target' => $this->faker->ipv4(),
            'type' => LookupType::Ip,
            'result_data' => $this->ipResultData(),
        ]);
    }

    public function domain(): static
    {
        return $this->state(fn () => [
            'target' => $this->faker->domainName(),
            'type' => LookupType::Domain,
            'result_data' => [
                'domain' => $this->faker->domainName(),
                'registrar' => $this->faker->company(),
                'created_date' => $this->faker->date(),
                'expires_date' => $this->faker->dateTimeBetween('now', '+3 years')->format('Y-m-d'),
                'name_servers' => [
                    'ns1.' . $this->faker->domainName(),
                    'ns2.' . $this->faker->domainName(),
                ],
                'status' => 'active',
            ],
        ]);
    }

    public function email(): static
    {
        return $this->state(fn () => [
            'target' => $this->faker->safeEmail(),
            'type' => LookupType::Email,
            'result_data' => [
                'email' => $this->faker->safeEmail(),
                'deliverable' => $this->faker->boolean(80),
                'disposable' => false,
                'mx_records' => [
                    'mx1.' . $this->faker->domainName(),
                ],
                'smtp_valid' => true,
            ],
        ]);
    }

    public function cached(): static
    {
        return $this->state(fn () => [
            'cached' => true,
            'lookup_time_ms' => $this->faker->randomFloat(2, 0.5, 5),
        ]);
    }

    private function ipResultData(): array
    {
        return [
            'ip' => $this->faker->ipv4(),
            'country' => $this->faker->countryCode(),
            'country_name' => $this->faker->country(),
            'city' => $this->faker->city(),
            'region' => $this->faker->state(),
            'latitude' => (float) $this->faker->latitude(),
            'longitude' => (float) $this->faker->longitude(),
            'isp' => $this->faker->company(),
            'org' => $this->faker->company(),
            'asn' => 'AS' . $this->faker->numberBetween(1000, 65000),
            'timezone' => $this->faker->timezone(),
        ];
    }
}
