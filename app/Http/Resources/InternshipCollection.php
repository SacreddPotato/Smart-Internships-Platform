<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class InternshipCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => InternshipResource::collection($this->collection),
        ];
    }

    public function paginationInformation($request, $paginated, $default): array
    {
        return [
            'links' => $default['links'],
            'meta' => [
                'current_page' => $default['meta']['current_page'],
                'last_page' => $default['meta']['last_page'],
                'per_page' => $default['meta']['per_page'],
                'total' => $default['meta']['total'],
            ],
        ];
    }
}
