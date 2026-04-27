<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InternshipResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'requirements' => $this->requirements,
            'location' => $this->location,
            'type' => $this->type?->value ?? $this->type,
            'status' => $this->status?->value ?? $this->status,
            'starts_at' => $this->starts_at?->toDateString(),
            'ends_at' => $this->ends_at?->toDateString(),
            'archived_at' => $this->archived_at?->toISOString(),
            'company' => new CompanyProfileResource($this->whenLoaded('company')),
            'skills' => SkillResource::collection($this->whenLoaded('skills')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
