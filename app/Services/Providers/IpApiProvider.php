<?php

namespace App\Services\Providers;

use App\Enums\LookupType;
use App\Exceptions\ProviderException;

final class IpApiProvider extends AbstractHttpProvider
{
    public function getName(): string
    {
        return 'ip_api';
    }

    protected function getConfigKey(): string
    {
        return 'ip_api';
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
     * @return array<string, mixed>
     *
     * @throws ProviderException
     */
    protected function doLookup(string $target, LookupType $type): array
    {
        $url = $this->getBaseUrl().'/'.$target;

        $response = $this->httpGet($url, [
            'fields' => 'status,message,country,countryCode,region,city,zip,lat,lon,timezone,isp,org,as,asname,proxy,hosting,query',
        ]);

        if (($response['status'] ?? '') === 'fail') {
            throw new ProviderException(
                $this->getName(),
                $response['message'] ?? 'Unknown error',
            );
        }

        return [
            'ip' => $response['query'] ?? $target,
            'risk_score' => 0,
            'is_vpn' => $response['hosting'] ?? false,
            'is_proxy' => $response['proxy'] ?? false,
            'is_tor' => false,
            'is_bot' => false,
            'country' => $response['countryCode'] ?? '',
            'city' => $response['city'] ?? '',
            'isp' => $response['isp'] ?? '',
            'asn' => $response['as'] ?? '',
            'abuse_reports' => 0,
            'blacklists' => [],
            'last_seen' => '',
        ];
    }
}
