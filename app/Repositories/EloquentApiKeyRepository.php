<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\ApiKeyRepositoryInterface;
use App\Models\ApiKey;

final class EloquentApiKeyRepository implements ApiKeyRepositoryInterface
{
    public function findByHash(string $keyHash): ?ApiKey
    {
        return ApiKey::where('key_hash', $keyHash)->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ApiKey
    {
        return ApiKey::create($data);
    }

    public function deactivate(int $id): bool
    {
        return (bool) ApiKey::where('id', $id)->update(['is_active' => false]);
    }
}
