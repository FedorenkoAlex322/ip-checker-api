<?php

declare(strict_types=1);

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
use App\Repositories\EloquentApiKeyRepository;
use App\Repositories\EloquentLookupResultRepository;
use App\Services\Cache\LookupCacheService;
use App\Services\CircuitBreaker\CircuitBreakerService;
use App\Services\Providers\ProviderRegistry;
use App\Services\Quota\QuotaService;
use App\Services\RateLimiter\RateLimiterService;
use App\Services\Retry\RetryService;
use Illuminate\Contracts\Foundation\Application;
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
                    if (($config['enabled'] ?? false) && isset($config['class'])) {
                        /** @var LookupProviderInterface $provider */
                        $provider = $app->make($config['class']);
                        $registry->register($provider);
                    }
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
        //
    }
}
