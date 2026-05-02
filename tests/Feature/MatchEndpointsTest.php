<?php

namespace Tests\Feature;

use App\Enums\InternshipStatus;
use App\Models\Internship;
use App\Models\Skill;
use App\Models\StudentProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MatchEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_get_match_score_for_an_internship(): void
    {
        $profile = StudentProfile::factory()->create(['gpa' => 3.40]);
        $internship = Internship::factory()->create();
        $matchedSkill = Skill::factory()->create(['name' => 'Laravel']);
        $missingSkill = Skill::factory()->create(['name' => 'React']);

        $profile->skills()->attach($matchedSkill);
        $internship->skills()->attach([$matchedSkill->id, $missingSkill->id]);

        Sanctum::actingAs($profile->user);

        $this->getJson("/api/v1/internships/{$internship->id}/match-score")
            ->assertOk()
            ->assertExactJson([
                'score' => 50,
            ]);
    }

    public function test_student_recommendations_returns_open_internships_ranked_by_match_score(): void
    {
        $profile = StudentProfile::factory()->create(['gpa' => 3.40]);
        $laravel = Skill::factory()->create(['name' => 'Laravel']);
        $react = Skill::factory()->create(['name' => 'React']);
        $sql = Skill::factory()->create(['name' => 'SQL']);

        $profile->skills()->attach([$laravel->id, $react->id]);

        $highMatch = Internship::factory()->create([
            'title' => 'High Match Intern',
            'status' => InternshipStatus::OPEN,
        ]);
        $highMatch->skills()->attach([$laravel->id, $react->id]);

        $mediumMatch = Internship::factory()->create([
            'title' => 'Medium Match Intern',
            'status' => InternshipStatus::OPEN,
        ]);
        $mediumMatch->skills()->attach([$laravel->id, $sql->id]);

        $closedMatch = Internship::factory()->create([
            'title' => 'Closed Match Intern',
            'status' => InternshipStatus::CLOSED,
        ]);
        $closedMatch->skills()->attach([$laravel->id, $react->id]);

        Sanctum::actingAs($profile->user);

        $response = $this->getJson('/api/v1/student/recommendations')
            ->assertOk()
            ->assertJsonPath('data.0.id', $highMatch->id)
            ->assertJsonPath('data.0.match_score', 100)
            ->assertJsonPath('data.1.id', $mediumMatch->id)
            ->assertJsonPath('data.1.match_score', 50);

        $recommendedInternshipIds = collect($response->json('data'))->pluck('id');

        $this->assertFalse($recommendedInternshipIds->contains($closedMatch->id));
    }
}
