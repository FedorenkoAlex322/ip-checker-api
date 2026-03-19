<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\LookupResult;
use App\Enums\LookupType;
use App\Models\LookupResult as LookupResultModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class LookupResultResource extends JsonResource
{
    public function __construct(
        private readonly LookupResult|LookupResultModel $lookupResult,
    ) {
        parent::__construct($lookupResult);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->lookupResult instanceof LookupResult) {
            return $this->fromDto($this->lookupResult);
        }

        return $this->fromModel($this->lookupResult);
    }

    /** @return array<string, mixed> */
    private function fromDto(LookupResult $dto): array
    {
        return [
            'data' => [
                'uuid' => $dto->uuid,
                'target' => $dto->target,
                'type' => $dto->type->value,
                'result' => $dto->resultData,
                'cached' => $dto->cached,
            ],
            'meta' => [
                'provider' => $dto->provider,
                'lookup_time_ms' => $dto->lookupTimeMs,
                'cached' => $dto->cached,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function fromModel(LookupResultModel $model): array
    {
        return [
            'data' => [
                'uuid' => $model->uuid,
                'target' => $model->target,
                'type' => $model->type instanceof LookupType ? $model->type->value : $model->type,
                'result' => $model->result_data,
                'cached' => $model->cached,
            ],
            'meta' => [
                'provider' => $model->provider,
                'lookup_time_ms' => $model->lookup_time_ms,
                'cached' => $model->cached,
            ],
        ];
    }
}
