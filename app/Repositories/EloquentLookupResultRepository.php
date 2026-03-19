<?php

namespace App\Repositories;

use App\Contracts\LookupResultRepositoryInterface;
use App\DTOs\LookupResult;
use App\Models\LookupResult as LookupResultModel;
use Illuminate\Pagination\LengthAwarePaginator;

final class EloquentLookupResultRepository implements LookupResultRepositoryInterface
{
    public function store(LookupResult $result, int $apiKeyId): LookupResultModel
    {
        return LookupResultModel::create([
            ...$result->toArray(),
            'api_key_id' => $apiKeyId,
        ]);
    }

    public function findByUuid(string $uuid): ?LookupResultModel
    {
        return LookupResultModel::where('uuid', $uuid)->first();
    }

    public function getHistory(int $apiKeyId, int $perPage = 20): LengthAwarePaginator
    {
        return LookupResultModel::where('api_key_id', $apiKeyId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
