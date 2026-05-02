<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\InternshipStatus;
use App\Models\Application;
use App\Models\Internship;
use App\Models\StudentProfile;

class ApplicationService
{
   public function apply(StudentProfile $profile, Internship $internship, array $data = []): Application {
        abort_if($internship->status !== InternshipStatus::OPEN, 422, 'This internship is not open for applications.');
        abort_if(
            Application::query()
                ->where('student_profile_id', $profile->id)
                ->where('internship_id', $internship->id)
                ->exists(),
            422,
            'You have already applied to this internship.',
        );

        return Application::create([
            'student_profile_id' => $profile->id,
            'internship_id' => $internship->id,
            'status' => ApplicationStatus::PENDING,
            // 'match_score' can be calculated later based on the student's profile and internship requirements
            'match_score' => MatchScoreService::calculate($profile, $internship),
            'message' => $data['message'] ?? null,
        ])->load(['studentProfile.user', 'studentProfile.skills', 'internship.company', 'internship.skills']);
   }
}
