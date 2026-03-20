<?php

namespace App\Services\Providers;

use App\Enums\LookupType;
use App\Exceptions\ProviderException;

final class VirusTotalProvider extends AbstractHttpProvider
{
    public function getName(): string
    {
        return 'virustotal';
    }

    protected function getConfigKey(): string
    {
        return 'virustotal';
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
        return [LookupType::Ip, LookupType::Domain];
    }

    /**
     * @return array<string, string>
     */
    protected function getHeaders(): array
    {
        return [
            'x-apikey' => $this->getApiKeyOrFail(),
            'Accept' => 'application/json',
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ProviderException
     */
    protected function doLookup(string $target, LookupType $type): array
    {
        $baseUrl = $this->getBaseUrl();

        $url = match ($type) {
            LookupType::Ip => "{$baseUrl}/ip_addresses/{$target}",
            LookupType::Domain => "{$baseUrl}/domains/{$target}",
            default => throw new ProviderException($this->getName(), "Unsupported lookup type: {$type->value}"),
        };

        $response = $this->httpGet($url);

        $attrs = $response['data']['attributes'] ?? [];

        return match ($type) {
            LookupType::Ip => $this->mapIpResponse($target, $attrs),
            LookupType::Domain => $this->mapDomainResponse($target, $attrs),
            default => throw new ProviderException($this->getName(), "Unsupported lookup type: {$type->value}"),
        };
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return array{risk_score: int, malicious: int, blacklists: array<int, string>}
     */
    private function extractThreatData(array $attrs): array
    {
        $stats = $attrs['last_analysis_stats'] ?? [];
        $total = is_array($stats) ? array_sum($stats) : 0;
        $malicious = ($stats['malicious'] ?? 0) + ($stats['suspicious'] ?? 0);
        $riskScore = $total > 0 ? (int) round(($malicious / $total) * 100) : 0;

        $results = $attrs['last_analysis_results'] ?? [];
        $blacklists = is_array($results)
            ? array_keys(array_filter($results, fn (mixed $r): bool => is_array($r) && ($r['category'] ?? '') === 'malicious'))
            : [];

        return [
            'risk_score' => $riskScore,
            'malicious' => $malicious,
            'blacklists' => array_slice($blacklists, 0, 10),
        ];
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return array<string, mixed>
     */
    private function mapIpResponse(string $target, array $attrs): array
    {
        $threat = $this->extractThreatData($attrs);

        return [
            'ip' => $target,
            'risk_score' => $threat['risk_score'],
            'is_vpn' => false,
            'is_proxy' => false,
            'is_tor' => false,
            'is_bot' => false,
            'country' => $attrs['country'] ?? '',
            'city' => '',
            'isp' => $attrs['as_owner'] ?? '',
            'asn' => (string) ($attrs['asn'] ?? ''),
            'abuse_reports' => $threat['malicious'],
            'blacklists' => $threat['blacklists'],
            'last_seen' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return array<string, mixed>
     */
    private function mapDomainResponse(string $target, array $attrs): array
    {
        $threat = $this->extractThreatData($attrs);
        $stats = $attrs['last_analysis_stats'] ?? [];

        return [
            'domain' => $target,
            'risk_score' => $threat['risk_score'],
            'is_malware' => ($stats['malicious'] ?? 0) > 0,
            'is_phishing' => false,
            'is_spam' => false,
            'category' => implode(', ', array_values($attrs['categories'] ?? [])),
            'registrar' => $attrs['registrar'] ?? '',
            'created_date' => isset($attrs['creation_date']) ? date('Y-m-d', $attrs['creation_date']) : '',
            'dns_records' => [
                'A' => [],
                'MX' => [],
                'NS' => [],
            ],
            'ssl_valid' => isset($attrs['last_https_certificate']['validity']),
            'blacklists' => $threat['blacklists'],
        ];
    }
}
