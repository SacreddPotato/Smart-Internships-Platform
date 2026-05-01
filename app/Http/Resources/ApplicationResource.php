<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
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
            'status' => $this->status->value,
            'match_score' => $this->match_score,
            'message' => $this->message,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'internship' => new InternshipResource($this->whenLoaded('internship')),
            'student' => new StudentProfileResource($this->whenLoaded('studentProfile')),
        ];
    }
}
