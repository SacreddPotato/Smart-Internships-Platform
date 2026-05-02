<?php

namespace Tests\Unit;

use App\Models\Internship;
use App\Models\Skill;
use App\Models\StudentProfile;
use App\Services\MatchScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchScoreServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_zero_when_student_has_no_skills(): void
    {
        $profile = StudentProfile::factory()->create();
        $internship = Internship::factory()->create();
        $internship->skills()->attach(Skill::factory()->count(2)->create());

        $score = MatchScoreService::calculate($profile, $internship);

        $this->assertSame(0, $score);
    }

    public function test_it_returns_default_score_when_internship_has_no_required_skills(): void
    {
        $profile = StudentProfile::factory()->create();
        $profile->skills()->attach(Skill::factory()->create());
        $internship = Internship::factory()->create();

        $score = MatchScoreService::calculate($profile, $internship);

        $this->assertSame(50, $score);
    }

    public function test_it_scores_the_percentage_of_matching_required_skills(): void
    {
        $profile = StudentProfile::factory()->create(['gpa' => 3.40]);
        $internship = Internship::factory()->create();
        $matchedSkills = Skill::factory()->count(2)->create();
        $missingSkills = Skill::factory()->count(2)->create();

        $profile->skills()->attach($matchedSkills);
        $internship->skills()->attach($matchedSkills->merge($missingSkills));

        $score = MatchScoreService::calculate($profile, $internship);

        $this->assertSame(50, $score);
    }

    public function test_it_adds_gpa_bonus_and_clamps_the_score_to_one_hundred(): void
    {
        $profile = StudentProfile::factory()->create(['gpa' => 3.80]);
        $internship = Internship::factory()->create();
        $skills = Skill::factory()->count(2)->create();

        $profile->skills()->attach($skills);
        $internship->skills()->attach($skills);

        $score = MatchScoreService::calculate($profile, $internship);

        $this->assertSame(100, $score);
    }
}
