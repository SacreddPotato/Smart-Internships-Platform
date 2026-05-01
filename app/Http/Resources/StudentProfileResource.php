<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class StudentProfileResource extends JsonResource
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
            'name' => $this->user->name,
            'email' => $this->user->email,
            'university' => $this->university,
            'major' => $this->major,
            'gpa' => $this->gpa === null ? null : (float) $this->gpa,
            'graduation_year' => $this->graduation_year,
            'bio' => $this->bio,
            'cv_path' => $this->cv_path,
            'cv_url' => $this->cv_path ? Storage::url($this->cv_path) : null,
            'skills' => SkillResource::collection($this->whenLoaded('skills')),
        ];
    }
}
