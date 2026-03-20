<?php

namespace Tests\Unit\Services\Providers;

use App\DTOs\LookupResult;
use App\Enums\LookupType;
use App\Exceptions\ProviderException;
use App\Services\Providers\IpInfoProvider;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class IpInfoProviderTest extends TestCase
{
    private IpInfoProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ip-checker.providers.ipinfo.api_key', 'ipinfo-test-token');
        config()->set('ip-checker.providers.ipinfo.base_url', 'https://ipinfo.io');
        config()->set('ip-checker.providers.ipinfo.timeout', 5);

        $this->provider = new IpInfoProvider;
    }

    #[Test]
    public function test_successful_ip_lookup(): void
    {
        // Arrange
        Http::fake([
            'ipinfo.io/8.8.8.8/json' => Http::response([
                'ip' => '8.8.8.8',
                'hostname' => 'dns.google',
                'city' => 'Mountain View',
                'region' => 'California',
                'country' => 'US',
                'loc' => '37.4056,-122.0775',
                'org' => 'AS15169 Google LLC',
                'postal' => '94043',
                'timezone' => 'America/Los_Angeles',
            ], 200),
        ]);

        // Act
        $result = $this->provider->lookup('8.8.8.8', LookupType::Ip);

        // Assert
        $this->assertInstanceOf(LookupResult::class, $result);
        $this->assertSame('8.8.8.8', $result->target);
        $this->assertSame('ipinfo', $result->provider);

        $data = $result->resultData;
        $this->assertSame('8.8.8.8', $data['ip']);
        $this->assertSame(0, $data['risk_score']);
        $this->assertSame('US', $data['country']);
        $this->assertSame('Mountain View', $data['city']);
        $this->assertSame('Google LLC', $data['isp']);
        $this->assertSame('AS15169', $data['asn']);
    }

    #[Test]
    public function test_error_response_throws_provider_exception(): void
    {
        // Arrange
        Http::fake([
            'ipinfo.io/8.8.8.8/json' => Http::response([
                'error' => [
                    'title' => 'Wrong IP',
                    'message' => 'Please provide a valid IP address',
                ],
            ], 200),
        ]);

        // Assert
        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Please provide a valid IP address');

        // Act
        $this->provider->lookup('8.8.8.8', LookupType::Ip);
    }

    #[Test]
    public function test_error_response_with_string_error_field(): void
    {
        // Arrange
        Http::fake([
            'ipinfo.io/8.8.8.8/json' => Http::response([
                'error' => 'Unauthorized',
            ], 200),
        ]);

        // Assert
        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Unauthorized');

        // Act
        $this->provider->lookup('8.8.8.8', LookupType::Ip);
    }

    #[Test]
    public function test_server_error_throws_provider_exception(): void
    {
        // Arrange
        Http::fake([
            'ipinfo.io/*' => Http::response('Internal Server Error', 500),
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
            'ipinfo.io/*' => Http::response('Too Many Requests', 429),
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
    public function test_is_enabled_true_even_without_api_key(): void
    {
        // Arrange
        config()->set('ip-checker.providers.ipinfo.api_key', null);
        $provider = new IpInfoProvider;

        // Assert - ipinfo does not require key
        $this->assertTrue($provider->isEnabled());
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
        $this->assertSame('ipinfo', $this->provider->getName());
    }
}
