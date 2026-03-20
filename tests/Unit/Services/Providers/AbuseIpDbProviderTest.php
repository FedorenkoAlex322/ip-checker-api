<?php

namespace Tests\Unit\Services\Providers;

use App\DTOs\LookupResult;
use App\Enums\LookupType;
use App\Exceptions\ProviderException;
use App\Services\Providers\AbuseIpDbProvider;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AbuseIpDbProviderTest extends TestCase
{
    private AbuseIpDbProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ip-checker.providers.abuseipdb.api_key', 'test-key-123');
        config()->set('ip-checker.providers.abuseipdb.base_url', 'https://api.abuseipdb.com/api/v2');
        config()->set('ip-checker.providers.abuseipdb.timeout', 10);

        $this->provider = new AbuseIpDbProvider;
    }

    #[Test]
    public function test_successful_ip_lookup(): void
    {
        // Arrange
        Http::fake([
            'api.abuseipdb.com/api/v2/check*' => Http::response([
                'data' => [
                    'ipAddress' => '8.8.8.8',
                    'isPublic' => true,
                    'abuseConfidenceScore' => 0,
                    'countryCode' => 'US',
                    'isp' => 'Google LLC',
                    'totalReports' => 12,
                    'isTor' => false,
                    'usageType' => 'Data Center/Web Hosting/Transit',
                    'lastReportedAt' => '2024-01-15T10:00:00+00:00',
                ],
            ], 200),
        ]);

        // Act
        $result = $this->provider->lookup('8.8.8.8', LookupType::Ip);

        // Assert
        $this->assertInstanceOf(LookupResult::class, $result);
        $this->assertSame('8.8.8.8', $result->target);
        $this->assertSame(LookupType::Ip, $result->type);
        $this->assertSame('abuseipdb', $result->provider);

        $data = $result->resultData;
        $this->assertSame('8.8.8.8', $data['ip']);
        $this->assertSame(0, $data['risk_score']);
        $this->assertTrue($data['is_proxy']);
        $this->assertFalse($data['is_tor']);
        $this->assertSame('US', $data['country']);
        $this->assertSame('Google LLC', $data['isp']);
        $this->assertSame(12, $data['abuse_reports']);
        $this->assertSame('2024-01-15T10:00:00+00:00', $data['last_seen']);
    }

    #[Test]
    public function test_server_error_throws_provider_exception(): void
    {
        // Arrange
        Http::fake([
            'api.abuseipdb.com/api/v2/check*' => Http::response('Internal Server Error', 500),
        ]);

        // Assert
        $this->expectException(ProviderException::class);

        // Act
        $this->provider->lookup('8.8.8.8', LookupType::Ip);
    }

    #[Test]
    public function test_rate_limit_throws_provider_exception(): void
    {
        // Arrange
        Http::fake([
            'api.abuseipdb.com/api/v2/check*' => Http::response('Too Many Requests', 429),
        ]);

        // Assert
        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        // Act
        $this->provider->lookup('8.8.8.8', LookupType::Ip);
    }

    #[Test]
    public function test_is_enabled_with_api_key(): void
    {
        // Assert
        $this->assertTrue($this->provider->isEnabled());
    }

    #[Test]
    public function test_is_enabled_without_api_key(): void
    {
        // Arrange
        config()->set('ip-checker.providers.abuseipdb.api_key', null);
        $provider = new AbuseIpDbProvider;

        // Assert
        $this->assertFalse($provider->isEnabled());
    }

    #[Test]
    public function test_supports_ip_lookup_only(): void
    {
        // Assert
        $this->assertTrue($this->provider->supports(LookupType::Ip));
        $this->assertFalse($this->provider->supports(LookupType::Domain));
        $this->assertFalse($this->provider->supports(LookupType::Email));
    }

    #[Test]
    public function test_provider_name(): void
    {
        // Assert
        $this->assertSame('abuseipdb', $this->provider->getName());
    }

    #[Test]
    public function test_is_proxy_false_when_usage_type_not_data_center(): void
    {
        // Arrange
        Http::fake([
            'api.abuseipdb.com/api/v2/check*' => Http::response([
                'data' => [
                    'ipAddress' => '1.2.3.4',
                    'abuseConfidenceScore' => 50,
                    'countryCode' => 'DE',
                    'isp' => 'Some ISP',
                    'totalReports' => 5,
                    'isTor' => false,
                    'usageType' => 'Fixed Line ISP',
                    'lastReportedAt' => null,
                ],
            ], 200),
        ]);

        // Act
        $result = $this->provider->lookup('1.2.3.4', LookupType::Ip);

        // Assert
        $this->assertFalse($result->resultData['is_proxy']);
    }
}
