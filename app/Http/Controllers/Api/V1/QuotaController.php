<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\QuotaServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\QuotaResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class QuotaController extends Controller
{
    public function __construct(
        private readonly QuotaServiceInterface $quotaService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var int $apiKeyId */
        $apiKeyId = $request->attributes->get('api_key_id');
        $quotaStatus = $this->quotaService->check($apiKeyId);

        return (new QuotaResource($quotaStatus))->response();
    }
}
