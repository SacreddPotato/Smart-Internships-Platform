<?php

namespace App\Services;

use App\Models\StudentProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StudentProfileService
{
    public function updateProfile(StudentProfile $profile, array $data): StudentProfile {
        $profile->update($data);

        return $profile->load(['user', 'skills']);
    }

    public function syncSkills(StudentProfile $profile, array $skillIds): StudentProfile {
        $profile->skills()->sync($skillIds);

        return $profile->load(['user', 'skills']);
    }

    public function uploadCv(StudentProfile $profile, UploadedFile $file): StudentProfile {
        if ($profile->cv_path) {
            Storage::disk('public')->delete($profile->cv_path);
        }

        $cvPath = $file->store('cvs', 'public');

        $profile->update(['cv_path' => $cvPath]);

        return $profile->load(['user', 'skills']);
    }
}
