<?php

namespace App\Console\Commands;

use App\Enums\ApiKeyTier;
use App\Services\ApiKey\ApiKeyService;
use Illuminate\Console\Command;

final class GenerateApiKeyCommand extends Command
{
    protected $signature = 'apikey:generate
        {--tier=free : API key tier (free/pro/enterprise)}
        {--name= : Key name/description}';

    protected $description = 'Generate a new API key';

    public function handle(ApiKeyService $apiKeyService): int
    {
        $tierValue = $this->option('tier');
        $tier = ApiKeyTier::tryFrom($tierValue);

        if ($tier === null) {
            $valid = implode(', ', array_column(ApiKeyTier::cases(), 'value'));
            $this->error("Invalid tier \"{$tierValue}\". Valid tiers: {$valid}");

            return self::FAILURE;
        }

        $name = $this->option('name') ?? "API Key - {$tier->label()}";

        $plaintext = $apiKeyService->generateKey($name, $tier);

        /** @var array{requests_per_minute: int, daily_limit: int, monthly_limit: int} $limits */
        $limits = config("rate-limiting.tiers.{$tier->value}");

        $this->newLine();
        $this->components->info('API key generated successfully!');
        $this->newLine();

        $this->components->twoColumnDetail('<fg=yellow;options=bold>Plaintext Key</>', "<fg=green;options=bold>{$plaintext}</>");
        $this->newLine();

        $this->components->twoColumnDetail('Name', $name);
        $this->components->twoColumnDetail('Tier', $tier->label());
        $this->components->twoColumnDetail('Rate Limit', "{$limits['requests_per_minute']} req/min");
        $this->components->twoColumnDetail('Daily Quota', number_format($limits['daily_limit']));
        $this->components->twoColumnDetail('Monthly Quota', number_format($limits['monthly_limit']));

        $this->newLine();
        $this->components->warn('Save this key now. It will NOT be shown again.');

        return self::SUCCESS;
    }
}
