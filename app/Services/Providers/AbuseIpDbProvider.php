<?php

namespace App\Services\Providers;

use App\Enums\LookupType;

final class AbuseIpDbProvider extends AbstractHttpProvider
{
    public function getName(): string
    {
        return 'abuseipdb';
    }

    protected function getConfigKey(): string
    {
        return 'abuseipdb';
    }

    public function requiresApiKey(): bool
    {
        return true;
    }

    /**
     * @return array<LookupType>
     */
    protected function supportedTypes(): array
    {
        return [LookupType::Ip];
    }

    /**
     * @return array<string, string>
     */
    protected function getHeaders(): array
    {
        return [
            'Key' => $this->getApiKeyOrFail(),
            'Accept' => 'application/json',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function doLookup(string $target, LookupType $type): array
    {
        $response = $this->httpGet("{$this->getBaseUrl()}/check", [
            'ipAddress' => $target,
            'maxAgeInDays' => 90,
            'verbose' => 'true',
        ]);

        $data = $response['data'] ?? [];

        return [
            'ip' => $data['ipAddress'] ?? $target,
            'risk_score' => $data['abuseConfidenceScore'] ?? 0,
            'is_vpn' => false,
            'is_proxy' => str_contains($data['usageType'] ?? '', 'Data Center'),
            'is_tor' => $data['isTor'] ?? false,
            'is_bot' => false,
            'country' => $data['countryCode'] ?? '',
            'city' => '',
            'isp' => $data['isp'] ?? '',
            'asn' => '',
            'abuse_reports' => $data['totalReports'] ?? 0,
            'blacklists' => [],
            'last_seen' => $data['lastReportedAt'] ?? '',
        ];
    }
}
