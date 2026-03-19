<?php

namespace App\Services\Providers;

use App\Enums\LookupType;

final class MockProvider extends AbstractLookupProvider
{
    public function getName(): string
    {
        return 'mock';
    }

    public function getPriority(): int
    {
        return (int) config('ip-checker.providers.mock.priority', 100);
    }

    public function isEnabled(): bool
    {
        return (bool) config('ip-checker.providers.mock.enabled', true);
    }

    protected function supportedTypes(): array
    {
        return [LookupType::Ip, LookupType::Domain, LookupType::Email];
    }

    protected function doLookup(string $target, LookupType $type): array
    {
        $delayMs = (int) config('ip-checker.providers.mock.delay_ms', 50);

        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }

        return match ($type) {
            LookupType::Ip => $this->mockIpLookup($target),
            LookupType::Domain => $this->mockDomainLookup($target),
            LookupType::Email => $this->mockEmailLookup($target),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function mockIpLookup(string $ip): array
    {
        $hash = crc32($ip);
        $riskScore = abs($hash) % 100;

        return [
            'ip' => $ip,
            'risk_score' => $riskScore,
            'is_vpn' => $riskScore > 70,
            'is_proxy' => $riskScore > 80,
            'is_tor' => $riskScore > 90,
            'is_bot' => $riskScore > 85,
            'country' => $this->mockCountry($hash),
            'city' => 'Mock City',
            'isp' => 'Mock ISP Inc.',
            'asn' => 'AS'.(abs($hash) % 99999),
            'abuse_reports' => abs($hash) % 50,
            'blacklists' => $riskScore > 60 ? ['spamhaus', 'barracuda'] : [],
            'last_seen' => now()->subHours(abs($hash) % 720)->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mockDomainLookup(string $domain): array
    {
        $hash = crc32($domain);
        $riskScore = abs($hash) % 100;

        return [
            'domain' => $domain,
            'risk_score' => $riskScore,
            'is_malware' => $riskScore > 85,
            'is_phishing' => $riskScore > 80,
            'is_spam' => $riskScore > 70,
            'category' => $riskScore > 60 ? 'suspicious' : 'clean',
            'registrar' => 'Mock Registrar LLC',
            'created_date' => now()->subDays(abs($hash) % 3650)->toDateString(),
            'dns_records' => [
                'A' => ['93.184.'.(abs($hash) % 255).'.'.(abs($hash + 1) % 255)],
                'MX' => ['mail.'.$domain],
                'NS' => ['ns1.mockdns.com', 'ns2.mockdns.com'],
            ],
            'ssl_valid' => $riskScore < 50,
            'blacklists' => $riskScore > 70 ? ['google_safe_browsing', 'phishtank'] : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mockEmailLookup(string $email): array
    {
        $hash = crc32($email);
        $riskScore = abs($hash) % 100;
        $parts = explode('@', $email);
        $domain = $parts[1] ?? 'unknown.com';

        return [
            'email' => $email,
            'risk_score' => $riskScore,
            'is_valid_format' => str_contains($email, '@'),
            'is_disposable' => $riskScore > 75,
            'is_role_based' => in_array($parts[0], ['admin', 'info', 'support', 'noreply'], true),
            'is_free_provider' => in_array($domain, ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com'], true),
            'is_deliverable' => $riskScore < 80,
            'has_mx_records' => $riskScore < 90,
            'domain' => $domain,
            'breach_count' => abs($hash) % 10,
            'first_seen' => now()->subDays(abs($hash) % 1825)->toDateString(),
            'reputation' => $riskScore < 30 ? 'high' : ($riskScore < 60 ? 'medium' : 'low'),
        ];
    }

    private function mockCountry(int $hash): string
    {
        $countries = ['US', 'GB', 'DE', 'FR', 'JP', 'CN', 'RU', 'BR', 'AU', 'CA'];

        return $countries[abs($hash) % count($countries)];
    }
}
