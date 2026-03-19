<?php

namespace Tests\Unit\Services;

use App\DTOs\LookupResult;
use App\Enums\LookupType;
use App\Services\Providers\MockProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MockProviderTest extends TestCase
{
    private MockProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        // Set delay to 0 to speed up tests
        config(['ip-checker.providers.mock.delay_ms' => 0]);

        $this->provider = new MockProvider;
    }

    #[Test]
    public function test_supports_all_lookup_types(): void
    {
        // Assert
        $this->assertTrue($this->provider->supports(LookupType::Ip));
        $this->assertTrue($this->provider->supports(LookupType::Domain));
        $this->assertTrue($this->provider->supports(LookupType::Email));
    }

    #[Test]
    public function test_returns_deterministic_results(): void
    {
        // Arrange
        $target = '8.8.8.8';
        $type = LookupType::Ip;

        // Act
        $result1 = $this->provider->lookup($target, $type);
        $result2 = $this->provider->lookup($target, $type);

        // Assert - same input should produce the same result_data (UUIDs will differ)
        $this->assertSame($result1->resultData, $result2->resultData);
    }

    #[Test]
    public function test_ip_lookup_returns_expected_structure(): void
    {
        // Act
        $result = $this->provider->lookup('192.168.1.1', LookupType::Ip);

        // Assert
        $data = $result->resultData;
        $this->assertArrayHasKey('ip', $data);
        $this->assertArrayHasKey('risk_score', $data);
        $this->assertArrayHasKey('is_vpn', $data);
        $this->assertArrayHasKey('is_proxy', $data);
        $this->assertArrayHasKey('is_tor', $data);
        $this->assertArrayHasKey('is_bot', $data);
        $this->assertArrayHasKey('country', $data);
        $this->assertArrayHasKey('city', $data);
        $this->assertArrayHasKey('isp', $data);
        $this->assertArrayHasKey('asn', $data);
        $this->assertArrayHasKey('abuse_reports', $data);
        $this->assertArrayHasKey('blacklists', $data);
        $this->assertArrayHasKey('last_seen', $data);

        $this->assertSame('192.168.1.1', $data['ip']);
        $this->assertIsInt($data['risk_score']);
        $this->assertGreaterThanOrEqual(0, $data['risk_score']);
        $this->assertLessThan(100, $data['risk_score']);
    }

    #[Test]
    public function test_domain_lookup_returns_expected_structure(): void
    {
        // Act
        $result = $this->provider->lookup('example.com', LookupType::Domain);

        // Assert
        $data = $result->resultData;
        $this->assertArrayHasKey('domain', $data);
        $this->assertArrayHasKey('risk_score', $data);
        $this->assertArrayHasKey('is_malware', $data);
        $this->assertArrayHasKey('is_phishing', $data);
        $this->assertArrayHasKey('is_spam', $data);
        $this->assertArrayHasKey('category', $data);
        $this->assertArrayHasKey('registrar', $data);
        $this->assertArrayHasKey('created_date', $data);
        $this->assertArrayHasKey('dns_records', $data);
        $this->assertArrayHasKey('ssl_valid', $data);
        $this->assertArrayHasKey('blacklists', $data);

        $this->assertSame('example.com', $data['domain']);
        $this->assertIsArray($data['dns_records']);
    }

    #[Test]
    public function test_email_lookup_returns_expected_structure(): void
    {
        // Act
        $result = $this->provider->lookup('user@example.com', LookupType::Email);

        // Assert
        $data = $result->resultData;
        $this->assertArrayHasKey('email', $data);
        $this->assertArrayHasKey('risk_score', $data);
        $this->assertArrayHasKey('is_valid_format', $data);
        $this->assertArrayHasKey('is_disposable', $data);
        $this->assertArrayHasKey('is_role_based', $data);
        $this->assertArrayHasKey('is_free_provider', $data);
        $this->assertArrayHasKey('is_deliverable', $data);
        $this->assertArrayHasKey('has_mx_records', $data);
        $this->assertArrayHasKey('domain', $data);
        $this->assertArrayHasKey('breach_count', $data);
        $this->assertArrayHasKey('first_seen', $data);
        $this->assertArrayHasKey('reputation', $data);

        $this->assertSame('user@example.com', $data['email']);
        $this->assertSame('example.com', $data['domain']);
        $this->assertTrue($data['is_valid_format']);
    }

    #[Test]
    public function test_returns_lookup_result_dto(): void
    {
        // Act
        $result = $this->provider->lookup('8.8.8.8', LookupType::Ip);

        // Assert
        $this->assertInstanceOf(LookupResult::class, $result);
        $this->assertSame('8.8.8.8', $result->target);
        $this->assertSame(LookupType::Ip, $result->type);
        $this->assertSame('mock', $result->provider);
        $this->assertIsFloat($result->lookupTimeMs);
        $this->assertFalse($result->cached);
        $this->assertNotEmpty($result->uuid);
    }
}
