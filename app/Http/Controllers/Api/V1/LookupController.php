<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\LookupType;
use App\Exceptions\LookupNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DomainLookupRequest;
use App\Http\Requests\EmailLookupRequest;
use App\Http\Requests\IpLookupRequest;
use App\Http\Resources\LookupResultCollection;
use App\Http\Resources\LookupResultResource;
use App\Services\Lookup\LookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LookupController extends Controller
{
    public function __construct(
        private readonly LookupService $lookupService,
    ) {}

    public function ip(IpLookupRequest $request): JsonResponse
    {
        $result = $this->lookupService->lookup(
            target: $request->validated('target'),
            type: LookupType::Ip,
            apiKeyId: $request->attributes->get('api_key_id'),
        );

        return (new LookupResultResource($result))->response();
    }

    public function domain(DomainLookupRequest $request): JsonResponse
    {
        $result = $this->lookupService->lookup(
            target: $request->validated('target'),
            type: LookupType::Domain,
            apiKeyId: $request->attributes->get('api_key_id'),
        );

        return (new LookupResultResource($result))->response();
    }

    public function email(EmailLookupRequest $request): JsonResponse
    {
        $result = $this->lookupService->lookup(
            target: $request->validated('target'),
            type: LookupType::Email,
            apiKeyId: $request->attributes->get('api_key_id'),
        );

        return (new LookupResultResource($result))->response();
    }

    public function show(string $uuid): JsonResponse
    {
        $result = $this->lookupService->findByUuid($uuid);

        if ($result === null) {
            throw new LookupNotFoundException($uuid);
        }

        return (new LookupResultResource($result))->response();
    }

    public function history(Request $request): JsonResponse
    {
        /** @var int $apiKeyId */
        $apiKeyId = $request->attributes->get('api_key_id');
        $perPage = min((int) $request->query('per_page', '20'), 100);

        $results = $this->lookupService->getHistory($apiKeyId, $perPage);

        return (new LookupResultCollection($results))->response();
    }
}
