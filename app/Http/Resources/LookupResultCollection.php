<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

final class LookupResultCollection extends ResourceCollection
{
    /** @var class-string */
    public $collects = LookupResultResource::class;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }

    /** @return array<string, mixed> */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return [
            'meta' => [
                'current_page' => $paginated['current_page'],
                'per_page' => $paginated['per_page'],
                'total' => $paginated['total'],
                'last_page' => $paginated['last_page'],
                'from' => $paginated['from'],
                'to' => $paginated['to'],
            ],
            'links' => [
                'first' => $paginated['first_page_url'],
                'last' => $paginated['last_page_url'],
                'prev' => $paginated['prev_page_url'],
                'next' => $paginated['next_page_url'],
            ],
        ];
    }
}
