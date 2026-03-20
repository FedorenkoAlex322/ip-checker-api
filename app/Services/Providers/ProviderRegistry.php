<?php

namespace App\Services\Providers;

use App\Contracts\CircuitBreakerInterface;
use App\Contracts\LookupProviderInterface;
use App\Contracts\ProviderRegistryInterface;
use App\Enums\LookupType;
use App\Exceptions\ProviderUnavailableException;

final class ProviderRegistry implements ProviderRegistryInterface
{
    /** @var array<LookupProviderInterface> */
    private array $providers = [];

    public function __construct(
        private readonly CircuitBreakerInterface $circuitBreaker,
    ) {}

    public function register(LookupProviderInterface $provider): void
    {
        $this->providers[] = $provider;

        usort(
            $this->providers,
            static fn (LookupProviderInterface $a, LookupProviderInterface $b): int => $a->getPriority() <=> $b->getPriority(),
        );
    }

    public function getProvider(LookupType $type, ?string $preferredProvider = null): LookupProviderInterface
    {
        if ($preferredProvider !== null) {
            foreach ($this->providers as $provider) {
                if (
                    $provider->getName() === $preferredProvider
                    && $this->isProviderAvailable($provider, $type)
                ) {
                    return $provider;
                }
            }
        }

        foreach ($this->providers as $provider) {
            if ($this->isProviderAvailable($provider, $type)) {
                return $provider;
            }
        }

        throw new ProviderUnavailableException(
            providerName: 'all',
            message: "No available provider for lookup type: {$type->value}",
        );
    }

    /**
     * @return array<LookupProviderInterface>
     */
    public function getAvailableProviders(LookupType $type): array
    {
        return array_values(array_filter(
            $this->providers,
            fn (LookupProviderInterface $provider): bool => $this->isProviderAvailable($provider, $type),
        ));
    }

    /**
     * @return array<LookupProviderInterface>
     */
    public function getAllProviders(): array
    {
        return $this->providers;
    }

    private function isProviderAvailable(LookupProviderInterface $provider, LookupType $type): bool
    {
        return $provider->supports($type)
            && $provider->isEnabled()
            && $this->circuitBreaker->isAvailable($provider->getName());
    }
}
