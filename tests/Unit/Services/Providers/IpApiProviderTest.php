<?php

namespace Tests\Unit\Services\Providers;

use App\DTOs\LookupResult;
use App\Enums\LookupType;
use App\Exceptions\ProviderException;
use App\Services\Providers\IpApiProvider;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class IpApiProviderTest extends TestCase
{
    private IpApiProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ip-checker.providers.ip_api.base_url', 'http://ip-api.com/json');
        config()->set('ip-checker.providers.ip_api.timeout', 5);

        $this->provider = new IpApiProvider;
    }

    #[Test]
    public function test_successful_ip_lookup(): void
    {
        // Arrange
        Http::fake([
            'ip-api.com/json/8.8.8.8*' => Http::response([
                'status' => 'success',
                'country' => 'United States',
                'countryCode' => 'US',
                'region' => 'CA',
                'city' => 'Mountain View',
                'zip' => '94043',
                'lat' => 37.4056,
                'lon' => -122.0775,
                'timezone' => 'America/Los_Angeles',
                'isp' => 'Google LLC',
                'org' => 'Google LLC',
                'as' => 'AS15169 Google LLC',
                'asname' => 'GOOGLE',
                'proxy' => false,
                'hosting' => true,
                'query' => '8.8.8.8',
            ], 200),
        ]);

        // Act
        $result = $this->provider->lookup('8.8.8.8', LookupType::Ip);

        // Assert
        $this->assertInstanceOf(LookupResult::class, $result);
        $this->assertSame('8.8.8.8', $result->target);
        $this->assertSame('ip_api', $result->provider);

        $data = $result->resultData;
        $this->assertSame('8.8.8.8', $data['ip']);
        $this->assertSame(0, $data['risk_score']);
        $this->assertTrue($data['is_vpn']);
        $this->assertFalse($data['is_proxy']);
        $this->assertFalse($data['is_tor']);
        $this->assertSame('US', $data['country']);
        $this->assertSame('Mountain View', $data['city']);
        $this->assertSame('Google LLC', $data['isp']);
        $this->assertSame('AS15169 Google LLC', $data['asn']);
    }

    #[Test]
    public function test_fail_status_throws_provider_exception(): void
    {
        // Arrange
        Http::fake([
            'ip-api.com/json/999.999.999.999*' => Http::response([
                'status' => 'fail',
                'message' => 'invalid query',
                'query' => '999.999.999.999',
            ], 200),
        ]);

        // Assert
        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('invalid query');

        // Act
        $this->provider->lookup('999.999.999.999', LookupType::Ip);
    }

    #[Test]
    public function test_server_error_throws_provider_exception(): void
    {
        // Arrange
        Http::fake([
            'ip-api.com/json/*' => Http::response('Internal Server Error', 500),
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
            'ip-api.com/json/*' => Http::response('Too Many Requests', 429),
        ]);

        // Assert
        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        // Act
        $this->provider->lookup('8.8.8.8', LookupType::Ip);
    }

    #[Test]
    public function test_is_enabled_always_true_no_key_required(): void
    {
        // Assert
        $this->assertTrue($this->provider->isEnabled());
    }

    #[Test]
    public function test_does_not_require_api_key(): void
    {
        // Assert
        $this->assertFalse($this->provider->requiresApiKey());
    }

    #[Test]
    public function test_supports_ip_only(): void
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
        $this->assertSame('ip_api', $this->provider->getName());
    }
}
