<?php

namespace App\Contracts;

use App\Enums\LookupType;

interface ProviderRegistryInterface
{
    public function register(LookupProviderInterface $provider): void;

    public function getProvider(LookupType $type, ?string $preferredProvider = null): LookupProviderInterface;

    /**
     * @return array<LookupProviderInterface>
     */
    public function getAvailableProviders(LookupType $type): array;
}
