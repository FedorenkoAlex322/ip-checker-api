<?php

namespace App\Services\Providers;

use App\Enums\LookupType;
use App\Exceptions\ProviderException;

final class IpInfoProvider extends AbstractHttpProvider
{
    public function getName(): string
    {
        return 'ipinfo';
    }

    protected function getConfigKey(): string
    {
        return 'ipinfo';
    }

    public function requiresApiKey(): bool
    {
        return false;
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
        $headers = [
            'Accept' => 'application/json',
        ];

        $apiKey = $this->getApiKey();

        if ($apiKey !== null) {
            $headers['Authorization'] = "Bearer {$apiKey}";
        }

        return $headers;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ProviderException
     */
    protected function doLookup(string $target, LookupType $type): array
    {
        $url = $this->getBaseUrl().'/'.$target.'/json';

        $response = $this->httpGet($url);

        if (array_key_exists('error', $response)) {
            $errorMessage = is_array($response['error'])
                ? ($response['error']['message'] ?? 'Unknown error')
                : (string) $response['error'];

            throw new ProviderException(
                $this->getName(),
                $errorMessage,
            );
        }

        $orgParts = explode(' ', $response['org'] ?? '', 2);
        $asn = $orgParts[0];
        $isp = $orgParts[1] ?? '';

        return [
            'ip' => $response['ip'] ?? $target,
            'risk_score' => 0,
            'is_vpn' => false,
            'is_proxy' => false,
            'is_tor' => false,
            'is_bot' => false,
            'country' => $response['country'] ?? '',
            'city' => $response['city'] ?? '',
            'isp' => $isp,
            'asn' => $asn,
            'abuse_reports' => 0,
            'blacklists' => [],
            'last_seen' => '',
        ];
    }
}
