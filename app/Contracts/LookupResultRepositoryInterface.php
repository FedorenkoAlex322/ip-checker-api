<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\LookupResult;
use App\Models\LookupResult as LookupResultModel;
use Illuminate\Pagination\LengthAwarePaginator;

interface LookupResultRepositoryInterface
{
    public function store(LookupResult $result, int $apiKeyId): LookupResultModel;

    public function findByUuid(string $uuid): ?LookupResultModel;

    public function getHistory(int $apiKeyId, int $perPage = 20): LengthAwarePaginator;
}
