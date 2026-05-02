<?php

namespace App\Services;

use App\Models\Internship;
use App\Models\StudentProfile;

class MatchScoreService
{
    public static function calculate(StudentProfile $profile, Internship $internship): int {
        $studentSkillIds = $profile->skills()->pluck('skills.id');
        $internshipSkillIds = $internship->skills()->pluck('skills.id');

        if ($studentSkillIds->isEmpty()) {
            return 0; // If the student has no skills, they cannot match any internship requirements
        }

        if ($internshipSkillIds->isEmpty()) {
            return 50; // If the internship has no required skills, give a default score
        } else {
            $matched = $internshipSkillIds->intersect($studentSkillIds)->count();
            $skillScore = (int) round(($matched / $internshipSkillIds->count()) * 100);
        }

        $gpaBonus = $profile->gpa !== null && (float) $profile->gpa >= 3.5 ? 10 : 0;
        return min(100, max(0, $skillScore + $gpaBonus));
    }
}
