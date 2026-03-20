<?php

namespace App\Services\Providers;

use App\Enums\LookupType;

final class EmailRepProvider extends AbstractHttpProvider
{
    private const array REPUTATION_SCORE_MAP = [
        'high' => 10,
        'medium' => 40,
        'low' => 70,
        'none' => 90,
    ];

    public function getName(): string
    {
        return 'emailrep';
    }

    protected function getConfigKey(): string
    {
        return 'emailrep';
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
        return [LookupType::Email];
    }

    /**
     * @return array<string, string>
     */
    protected function getHeaders(): array
    {
        $headers = [
            'User-Agent' => 'IpCheckerApi/1.0',
            'Accept' => 'application/json',
        ];

        $apiKey = $this->getApiKey();

        if ($apiKey !== null) {
            $headers['Key'] = $apiKey;
        }

        return $headers;
    }

    /**
     * @return array<string, mixed>
     */
    protected function doLookup(string $target, LookupType $type): array
    {
        $data = $this->httpGet("{$this->getBaseUrl()}/{$target}");

        $details = $data['details'] ?? [];

        $riskScore = self::REPUTATION_SCORE_MAP[$data['reputation'] ?? 'none'] ?? 50;

        if ($data['suspicious'] ?? false) {
            $riskScore = max($riskScore, 80);
        }

        $breachCount = 0;

        if ($details['credentials_leaked'] ?? false) {
            $breachCount = ($details['credentials_leaked_recent'] ?? false) ? 5 : 1;
        }

        return [
            'email' => $data['email'] ?? $target,
            'risk_score' => $riskScore,
            'is_valid_format' => true,
            'is_disposable' => $details['disposable'] ?? false,
            'is_role_based' => false,
            'is_free_provider' => $details['free_provider'] ?? false,
            'is_deliverable' => $details['deliverable'] ?? true,
            'has_mx_records' => $details['accept_all'] ?? true,
            'domain' => explode('@', $target)[1] ?? '',
            'breach_count' => $breachCount,
            'first_seen' => $details['first_seen'] ?? '',
            'reputation' => $data['reputation'] ?? 'none',
        ];
    }
}
