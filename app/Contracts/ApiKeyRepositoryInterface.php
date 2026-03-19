<?php

namespace App\Contracts;

use App\Models\ApiKey;

interface ApiKeyRepositoryInterface
{
    public function findByHash(string $keyHash): ?ApiKey;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ApiKey;

    public function deactivate(int $id): bool;
}
