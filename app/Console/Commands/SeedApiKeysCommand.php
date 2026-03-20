<?php

namespace App\Console\Commands;

use App\Enums\ApiKeyTier;
use App\Services\ApiKey\ApiKeyService;
use Illuminate\Console\Command;

final class SeedApiKeysCommand extends Command
{
    protected $signature = 'apikey:seed';

    protected $description = 'Generate a set of API keys for all tiers (development/testing)';

    /** @var array<array{name: string, tier: ApiKeyTier}> */
    private const array SEED_KEYS = [
        ['name' => 'Test Free', 'tier' => ApiKeyTier::Free],
        ['name' => 'Test Pro', 'tier' => ApiKeyTier::Pro],
        ['name' => 'Test Enterprise', 'tier' => ApiKeyTier::Enterprise],
    ];

    public function handle(ApiKeyService $apiKeyService): int
    {
        $this->components->info('Generating API keys for all tiers...');
        $this->newLine();

        $rows = [];

        foreach (self::SEED_KEYS as $seed) {
            $plaintext = $apiKeyService->generateKey($seed['name'], $seed['tier']);

            /** @var array{requests_per_minute: int, daily_limit: int, monthly_limit: int} $limits */
            $limits = config("rate-limiting.tiers.{$seed['tier']->value}");

            $rows[] = [
                $seed['name'],
                $seed['tier']->label(),
                $plaintext,
                "{$limits['requests_per_minute']} req/min",
                number_format($limits['daily_limit']),
                number_format($limits['monthly_limit']),
            ];
        }

        $this->table(
            ['Name', 'Tier', 'Key', 'Rate Limit', 'Daily Quota', 'Monthly Quota'],
            $rows,
        );

        $this->newLine();
        $this->components->warn('Save these keys now. They will NOT be shown again.');

        return self::SUCCESS;
    }
}
