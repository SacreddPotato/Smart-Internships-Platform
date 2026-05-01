<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Arr;

class InternshipCollection extends ResourceCollection
{
    public $collects = InternshipResource::class;

    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }

    public function paginationInformation($request, $paginated, $default): array
    {
        return [
            'links' => Arr::get($default, 'links', []),
            'meta' => [
                'current_page' => Arr::get($paginated, 'current_page'),
                'from' => Arr::get($paginated, 'from'),
                'last_page' => Arr::get($paginated, 'last_page'),
                'path' => Arr::get($paginated, 'path'),
                'per_page' => Arr::get($paginated, 'per_page'),
                'to' => Arr::get($paginated, 'to'),
                'total' => Arr::get($paginated, 'total'),
            ],
        ];
    }
}
