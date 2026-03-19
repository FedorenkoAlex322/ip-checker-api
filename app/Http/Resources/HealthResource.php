<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class HealthResource extends JsonResource
{
    /**
     * Expected $resource shape:
     * [
     *   'status'           => 'healthy'|'degraded'|'unhealthy',
     *   'services'         => ['mysql' => 'up'|'down', 'redis' => 'up'|'down', ...],
     *   'circuit_breakers' => ['mock' => 'closed'|'open'|'half-open', ...],
     *   'timestamp'        => \DateTimeInterface|string,
     * ]
     *
     * @param  array<string, mixed>  $resource
     */
    public function __construct(array $resource)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $health */
        $health = $this->resource;

        $timestamp = $health['timestamp'] ?? now();

        if ($timestamp instanceof \DateTimeInterface) {
            $timestamp = $timestamp->format(\DateTimeInterface::ATOM);
        }

        return [
            'data' => [
                'status' => $health['status'],
                'services' => $health['services'] ?? [],
                'circuit_breakers' => $health['circuit_breakers'] ?? [],
                'timestamp' => $timestamp,
            ],
        ];
    }
}
