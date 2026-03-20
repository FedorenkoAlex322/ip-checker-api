<?php

namespace App\Console\Commands;

use App\Contracts\LookupProviderInterface;
use App\Contracts\ProviderRegistryInterface;
use App\Enums\LookupType;
use App\Services\Providers\AbstractHttpProvider;
use Illuminate\Console\Command;
use Throwable;

final class CheckProvidersCommand extends Command
{
    protected $signature = 'providers:check';

    protected $description = 'Check availability and connectivity of all lookup providers';

    private const array TEST_TARGETS = [
        'ip' => '8.8.8.8',
        'domain' => 'google.com',
        'email' => 'test@google.com',
    ];

    public function handle(ProviderRegistryInterface $registry): int
    {
        $this->info('Checking providers...');
        $this->newLine();

        $providers = $registry->getAllProviders();

        if ($providers === []) {
            $this->warn('No providers registered.');

            return self::FAILURE;
        }

        $rows = [];
        $operational = 0;
        $total = count($providers);

        foreach ($providers as $provider) {
            $types = $this->getSupportedTypes($provider);
            $apiKeyStatus = $this->getApiKeyStatus($provider);
            $connectivity = $this->testConnectivity($provider, $types);

            if ($connectivity['ok']) {
                $operational++;
            }

            $rows[] = [
                $provider->getName(),
                implode(', ', array_map(fn (LookupType $t): string => $t->value, $types)),
                $apiKeyStatus,
                $connectivity['message'],
            ];
        }

        $this->table(
            ['Provider', 'Types', 'API Key', 'Connectivity'],
            $rows,
        );

        $this->newLine();

        if ($operational === $total) {
            $this->info("Summary: {$operational}/{$total} providers operational");
        } else {
            $this->error("Summary: {$operational}/{$total} providers operational");
        }

        return $operational === $total ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array<LookupType>
     */
    private function getSupportedTypes(LookupProviderInterface $provider): array
    {
        return array_filter(
            LookupType::cases(),
            fn (LookupType $type): bool => $provider->supports($type),
        );
    }

    private function getApiKeyStatus(LookupProviderInterface $provider): string
    {
        if (! $provider instanceof AbstractHttpProvider) {
            return '-';
        }

        if ($provider->requiresApiKey()) {
            return $provider->getApiKey() !== null ? "\u{2713}" : "\u{2717} MISSING";
        }

        // Key is optional — show whether it's configured
        if ($provider->getApiKey() !== null) {
            return "\u{2713} optional";
        }

        return '~ no key';
    }

    /**
     * @param  array<LookupType>  $supportedTypes
     * @return array{ok: bool, message: string}
     */
    private function testConnectivity(LookupProviderInterface $provider, array $supportedTypes): array
    {
        if (! $provider->isEnabled()) {
            return ['ok' => false, 'message' => 'SKIP (disabled)'];
        }

        if ($supportedTypes === []) {
            return ['ok' => false, 'message' => 'SKIP (no types)'];
        }

        $type = reset($supportedTypes);
        $target = self::TEST_TARGETS[$type->value];

        try {
            $start = microtime(true);
            $provider->lookup($target, $type);
            $elapsedMs = (int) round((microtime(true) - $start) * 1000);

            return ['ok' => true, 'message' => "OK ({$elapsedMs}ms)"];
        } catch (Throwable $e) {
            $reason = $e->getMessage();

            // Hint about optional API key when rate-limited without one
            $hasNoKey = $provider instanceof AbstractHttpProvider
                && ! $provider->requiresApiKey()
                && $provider->getApiKey() === null;

            if ($hasNoKey && str_contains($reason, 'Rate limit')) {
                return ['ok' => false, 'message' => 'FAILED: Rate limited (configure API key for higher limits)'];
            }

            if (mb_strlen($reason) > 60) {
                $reason = mb_substr($reason, 0, 57).'...';
            }

            return ['ok' => false, 'message' => "FAILED: {$reason}"];
        }
    }
}
