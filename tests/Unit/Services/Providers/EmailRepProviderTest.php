<?php

namespace Tests\Unit\Services\Providers;

use App\DTOs\LookupResult;
use App\Enums\LookupType;
use App\Exceptions\ProviderException;
use App\Services\Providers\EmailRepProvider;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class EmailRepProviderTest extends TestCase
{
    private EmailRepProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ip-checker.providers.emailrep.api_key', 'emailrep-test-key');
        config()->set('ip-checker.providers.emailrep.base_url', 'https://emailrep.io');
        config()->set('ip-checker.providers.emailrep.timeout', 10);

        $this->provider = new EmailRepProvider;
    }

    #[Test]
    public function test_successful_email_lookup(): void
    {
        // Arrange
        Http::fake([
            'emailrep.io/test@example.com' => Http::response([
                'email' => 'test@example.com',
                'reputation' => 'medium',
                'suspicious' => false,
                'references' => 10,
                'details' => [
                    'disposable' => false,
                    'free_provider' => false,
                    'deliverable' => true,
                    'accept_all' => true,
                    'credentials_leaked' => true,
                    'credentials_leaked_recent' => false,
                    'first_seen' => '2020-01-01',
                ],
            ], 200),
        ]);

        // Act
        $result = $this->provider->lookup('test@example.com', LookupType::Email);

        // Assert
        $this->assertInstanceOf(LookupResult::class, $result);
        $this->assertSame('test@example.com', $result->target);
        $this->assertSame('emailrep', $result->provider);

        $data = $result->resultData;
        $this->assertSame('test@example.com', $data['email']);
        $this->assertSame(40, $data['risk_score']); // medium = 40
        $this->assertTrue($data['is_valid_format']);
        $this->assertFalse($data['is_disposable']);
        $this->assertFalse($data['is_free_provider']);
        $this->assertTrue($data['is_deliverable']);
        $this->assertTrue($data['has_mx_records']);
        $this->assertSame('example.com', $data['domain']);
        $this->assertSame(1, $data['breach_count']); // leaked but not recent
        $this->assertSame('2020-01-01', $data['first_seen']);
        $this->assertSame('medium', $data['reputation']);
    }

    #[Test]
    public function test_suspicious_email_has_high_risk_score(): void
    {
        // Arrange
        Http::fake([
            'emailrep.io/suspicious@test.com' => Http::response([
                'email' => 'suspicious@test.com',
                'reputation' => 'high',
                'suspicious' => true,
                'references' => 0,
                'details' => [
                    'disposable' => true,
                    'free_provider' => true,
                    'deliverable' => false,
                    'accept_all' => false,
                    'credentials_leaked' => false,
                    'credentials_leaked_recent' => false,
                    'first_seen' => '',
                ],
            ], 200),
        ]);

        // Act
        $result = $this->provider->lookup('suspicious@test.com', LookupType::Email);

        // Assert
        $data = $result->resultData;
        // high reputation = 10, but suspicious = true bumps to max(10, 80) = 80
        $this->assertSame(80, $data['risk_score']);
        $this->assertTrue($data['is_disposable']);
        $this->assertTrue($data['is_free_provider']);
        $this->assertFalse($data['is_deliverable']);
    }

    #[Test]
    public function test_recent_credentials_leak_sets_breach_count_5(): void
    {
        // Arrange
        Http::fake([
            'emailrep.io/leaked@test.com' => Http::response([
                'email' => 'leaked@test.com',
                'reputation' => 'low',
                'suspicious' => false,
                'references' => 2,
                'details' => [
                    'disposable' => false,
                    'free_provider' => false,
                    'deliverable' => true,
                    'accept_all' => true,
                    'credentials_leaked' => true,
                    'credentials_leaked_recent' => true,
                    'first_seen' => '2023-06-01',
                ],
            ], 200),
        ]);

        // Act
        $result = $this->provider->lookup('leaked@test.com', LookupType::Email);

        // Assert
        $this->assertSame(5, $result->resultData['breach_count']);
        $this->assertSame(70, $result->resultData['risk_score']); // low = 70
    }

    #[Test]
    public function test_server_error_throws_provider_exception(): void
    {
        // Arrange
        Http::fake([
            'emailrep.io/*' => Http::response('Internal Server Error', 500),
        ]);

        // Assert
        $this->expectException(ProviderException::class);

        // Act
        $this->provider->lookup('test@example.com', LookupType::Email);
    }

    #[Test]
    public function test_rate_limit_throws_provider_exception(): void
    {
        // Arrange
        Http::fake([
            'emailrep.io/*' => Http::response('Too Many Requests', 429),
        ]);

        // Assert
        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        // Act
        $this->provider->lookup('test@example.com', LookupType::Email);
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
        config()->set('ip-checker.providers.emailrep.api_key', null);
        $provider = new EmailRepProvider;

        // Assert - emailrep does not require key
        $this->assertTrue($provider->isEnabled());
    }

    #[Test]
    public function test_does_not_require_api_key(): void
    {
        // Assert
        $this->assertFalse($this->provider->requiresApiKey());
    }

    #[Test]
    public function test_supports_email_only(): void
    {
        // Assert
        $this->assertFalse($this->provider->supports(LookupType::Ip));
        $this->assertFalse($this->provider->supports(LookupType::Domain));
        $this->assertTrue($this->provider->supports(LookupType::Email));
    }

    #[Test]
    public function test_provider_name(): void
    {
        // Assert
        $this->assertSame('emailrep', $this->provider->getName());
    }

    #[Test]
    public function test_none_reputation_gives_high_risk_score(): void
    {
        // Arrange
        Http::fake([
            'emailrep.io/unknown@test.com' => Http::response([
                'email' => 'unknown@test.com',
                'reputation' => 'none',
                'suspicious' => false,
                'references' => 0,
                'details' => [
                    'disposable' => false,
                    'free_provider' => false,
                    'deliverable' => true,
                    'accept_all' => true,
                    'credentials_leaked' => false,
                    'credentials_leaked_recent' => false,
                    'first_seen' => '',
                ],
            ], 200),
        ]);

        // Act
        $result = $this->provider->lookup('unknown@test.com', LookupType::Email);

        // Assert
        $this->assertSame(90, $result->resultData['risk_score']); // none = 90
        $this->assertSame(0, $result->resultData['breach_count']);
    }
}
