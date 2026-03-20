<?php

namespace Tests\Unit\Services\Providers;

use App\DTOs\LookupResult;
use App\Enums\LookupType;
use App\Exceptions\ProviderException;
use App\Services\Providers\VirusTotalProvider;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class VirusTotalProviderTest extends TestCase
{
    private VirusTotalProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ip-checker.providers.virustotal.api_key', 'vt-test-key-456');
        config()->set('ip-checker.providers.virustotal.base_url', 'https://www.virustotal.com/api/v3');
        config()->set('ip-checker.providers.virustotal.timeout', 15);

        $this->provider = new VirusTotalProvider;
    }

    #[Test]
    public function test_successful_ip_lookup(): void
    {
        // Arrange
        Http::fake([
            'www.virustotal.com/api/v3/ip_addresses/8.8.8.8' => Http::response([
                'data' => [
                    'id' => '8.8.8.8',
                    'type' => 'ip_address',
                    'attributes' => [
                        'country' => 'US',
                        'as_owner' => 'Google LLC',
                        'asn' => 15169,
                        'last_analysis_stats' => [
                            'malicious' => 2,
                            'suspicious' => 1,
                            'harmless' => 80,
                            'undetected' => 5,
                        ],
                        'last_analysis_results' => [
                            'vendor1' => ['category' => 'malicious', 'result' => 'malware'],
                            'vendor2' => ['category' => 'harmless', 'result' => 'clean'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        // Act
        $result = $this->provider->lookup('8.8.8.8', LookupType::Ip);

        // Assert
        $this->assertInstanceOf(LookupResult::class, $result);
        $this->assertSame('8.8.8.8', $result->target);
        $this->assertSame('virustotal', $result->provider);

        $data = $result->resultData;
        $this->assertSame('8.8.8.8', $data['ip']);
        $this->assertSame('US', $data['country']);
        $this->assertSame('Google LLC', $data['isp']);
        $this->assertSame('15169', $data['asn']);
        // risk_score = round((2+1)/(2+1+80+5) * 100) = round(3.41) = 3
        $this->assertSame(3, $data['risk_score']);
        $this->assertSame(3, $data['abuse_reports']);
        $this->assertContains('vendor1', $data['blacklists']);
        $this->assertNotContains('vendor2', $data['blacklists']);
    }

    #[Test]
    public function test_successful_domain_lookup(): void
    {
        // Arrange
        Http::fake([
            'www.virustotal.com/api/v3/domains/example.com' => Http::response([
                'data' => [
                    'id' => 'example.com',
                    'type' => 'domain',
                    'attributes' => [
                        'registrar' => 'Example Registrar',
                        'creation_date' => 1234567890,
                        'categories' => ['Forcepoint' => 'technology'],
                        'last_analysis_stats' => [
                            'malicious' => 0,
                            'suspicious' => 0,
                            'harmless' => 85,
                            'undetected' => 3,
                        ],
                        'last_analysis_results' => [],
                        'last_https_certificate' => [
                            'validity' => ['not_after' => '2025-01-01'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        // Act
        $result = $this->provider->lookup('example.com', LookupType::Domain);

        // Assert
        $data = $result->resultData;
        $this->assertSame('example.com', $data['domain']);
        $this->assertSame(0, $data['risk_score']);
        $this->assertFalse($data['is_malware']);
        $this->assertSame('technology', $data['category']);
        $this->assertSame('Example Registrar', $data['registrar']);
        $this->assertSame('2009-02-13', $data['created_date']);
        $this->assertTrue($data['ssl_valid']);
        $this->assertEmpty($data['blacklists']);
    }

    #[Test]
    public function test_server_error_throws_provider_exception(): void
    {
        // Arrange
        Http::fake([
            'www.virustotal.com/api/v3/ip_addresses/*' => Http::response('Internal Server Error', 500),
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
            'www.virustotal.com/api/v3/ip_addresses/*' => Http::response('Too Many Requests', 429),
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
        config()->set('ip-checker.providers.virustotal.api_key', null);
        $provider = new VirusTotalProvider;

        // Assert
        $this->assertFalse($provider->isEnabled());
    }

    #[Test]
    public function test_supports_ip_and_domain(): void
    {
        // Assert
        $this->assertTrue($this->provider->supports(LookupType::Ip));
        $this->assertTrue($this->provider->supports(LookupType::Domain));
        $this->assertFalse($this->provider->supports(LookupType::Email));
    }

    #[Test]
    public function test_provider_name(): void
    {
        // Assert
        $this->assertSame('virustotal', $this->provider->getName());
    }

    #[Test]
    public function test_ip_lookup_uses_correct_endpoint(): void
    {
        // Arrange
        Http::fake([
            'www.virustotal.com/api/v3/ip_addresses/1.2.3.4' => Http::response([
                'data' => ['attributes' => ['last_analysis_stats' => []]],
            ], 200),
        ]);

        // Act
        $this->provider->lookup('1.2.3.4', LookupType::Ip);

        // Assert
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/ip_addresses/1.2.3.4');
        });
    }

    #[Test]
    public function test_domain_lookup_uses_correct_endpoint(): void
    {
        // Arrange
        Http::fake([
            'www.virustotal.com/api/v3/domains/example.com' => Http::response([
                'data' => ['attributes' => ['last_analysis_stats' => []]],
            ], 200),
        ]);

        // Act
        $this->provider->lookup('example.com', LookupType::Domain);

        // Assert
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/domains/example.com');
        });
    }
}
