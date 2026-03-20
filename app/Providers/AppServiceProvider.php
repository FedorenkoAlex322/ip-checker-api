<?php

namespace App\Providers;

use App\Contracts\ApiKeyRepositoryInterface;
use App\Contracts\CircuitBreakerInterface;
use App\Contracts\LookupCacheInterface;
use App\Contracts\LookupProviderInterface;
use App\Contracts\LookupResultRepositoryInterface;
use App\Contracts\ProviderRegistryInterface;
use App\Contracts\QuotaServiceInterface;
use App\Contracts\RateLimiterInterface;
use App\Contracts\RetryableInterface;
use App\Events\ApiKeyCreated;
use App\Events\CircuitBreakerStateChanged;
use App\Events\LookupCompleted;
use App\Events\LookupFailed;
use App\Events\QuotaExceeded;
use App\Listeners\CacheResultListener;
use App\Listeners\IncrementQuotaListener;
use App\Listeners\LogApiKeyCreatedListener;
use App\Listeners\LogCircuitBreakerChangeListener;
use App\Listeners\LogLookupFailureListener;
use App\Listeners\LogLookupListener;
use App\Listeners\LogQuotaExceededListener;
use App\Repositories\EloquentApiKeyRepository;
use App\Repositories\EloquentLookupResultRepository;
use App\Services\Cache\LookupCacheService;
use App\Services\CircuitBreaker\CircuitBreakerService;
use App\Services\Providers\AbstractHttpProvider;
use App\Services\Providers\ProviderRegistry;
use App\Services\Quota\QuotaService;
use App\Services\RateLimiter\RateLimiterService;
use App\Services\Retry\RetryService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Circuit Breaker — singleton (shared state across the request lifecycle)
        $this->app->singleton(
            CircuitBreakerInterface::class,
            CircuitBreakerService::class,
        );

        // Rate Limiter
        $this->app->bind(
            RateLimiterInterface::class,
            RateLimiterService::class,
        );

        // Lookup Cache
        $this->app->bind(
            LookupCacheInterface::class,
            LookupCacheService::class,
        );

        // Quota Service
        $this->app->bind(
            QuotaServiceInterface::class,
            QuotaService::class,
        );

        // Retry Service
        $this->app->bind(
            RetryableInterface::class,
            RetryService::class,
        );

        // Repositories
        $this->app->bind(
            ApiKeyRepositoryInterface::class,
            EloquentApiKeyRepository::class,
        );

        $this->app->bind(
            LookupResultRepositoryInterface::class,
            EloquentLookupResultRepository::class,
        );

        // Provider Registry — singleton (holds registered provider instances)
        $this->app->singleton(
            ProviderRegistryInterface::class,
            function (Application $app): ProviderRegistry {
                $registry = new ProviderRegistry(
                    $app->make(CircuitBreakerInterface::class),
                );

                /** @var array<string, array{enabled?: bool, class?: class-string<LookupProviderInterface>}> $providers */
                $providers = config('ip-checker.providers', []);

                foreach ($providers as $config) {
                    if (! isset($config['class'])) {
                        continue;
                    }

                    // Mock provider: respect the 'enabled' flag
                    // Real providers: always instantiate (isEnabled() handles API key check)
                    if (array_key_exists('enabled', $config) && ! $config['enabled']) {
                        continue;
                    }

                    /** @var LookupProviderInterface $provider */
                    $provider = $app->make($config['class']);
                    $registry->register($provider);
                }

                return $registry;
            },
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Event-Listener mappings
        Event::listen(LookupCompleted::class, LogLookupListener::class);
        Event::listen(LookupCompleted::class, IncrementQuotaListener::class);
        Event::listen(LookupCompleted::class, CacheResultListener::class);
        Event::listen(LookupFailed::class, LogLookupFailureListener::class);
        Event::listen(CircuitBreakerStateChanged::class, LogCircuitBreakerChangeListener::class);
        Event::listen(QuotaExceeded::class, LogQuotaExceededListener::class);
        Event::listen(ApiKeyCreated::class, LogApiKeyCreatedListener::class);

        // Warn about misconfigured providers (missing API keys)
        $this->app->booted(function (): void {
            $envVarMap = [
                'abuseipdb' => 'ABUSEIPDB_API_KEY',
                'virustotal' => 'VIRUSTOTAL_API_KEY',
                'ipinfo' => 'IPINFO_TOKEN',
                'emailrep' => 'EMAILREP_API_KEY',
            ];

            /** @var ProviderRegistryInterface $registry */
            $registry = $this->app->make(ProviderRegistryInterface::class);

            foreach ($registry->getAllProviders() as $provider) {
                if (! $provider instanceof AbstractHttpProvider) {
                    continue;
                }

                if ($provider->requiresApiKey() && $provider->getApiKey() === null) {
                    $envVar = $envVarMap[$provider->getName()] ?? strtoupper($provider->getName()).'_API_KEY';

                    Log::warning("Provider [{$provider->getName()}] requires an API key but none is configured. Set {$envVar} in .env. Provider will be skipped.");
                }
            }
        });
    }
}
