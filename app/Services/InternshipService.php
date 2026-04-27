<?php

namespace App\Services;

use App\Enums\InternshipStatus;
use App\Models\Internship;
use App\Models\User;
use \Illuminate\Support\Arr;

class InternshipService
{
    public function create(User $user, array $data): Internship {
        $companyProfile = $user->companyProfile;

        abort_if(! $companyProfile, 422, 'COmpany profile is required before creating an internship');

        $skillIds = Arr::pull($data, 'skills', []);
        $internship = $companyProfile->internships()->create([
            ...$data,
            'status' => InternshipStatus::OPEN
        ]);

        $internship->skills()->attach($skillIds);

        return $internship->load(['company', 'skills']);
    }

    public function update(Internship $internship, array $data): Internship {
        $skillIds = Arr::pull($data, 'skills', null);

        $internship->update($data);

        if ($skillIds !== null) {
            $internship->skills()->sync($skillIds);
        }

        return $internship->load(['company', 'skills']);
    }

    public function archive(Internship $internship): Internship {
        $internship->update([
            'status' => InternshipStatus::ARCHIVED,
            'archived_at' => now()
        ]);

        return $internship->load(['company', 'skills']);
    }

    public function delete(Internship $internship): void {
        $internship->delete();
    }
}
