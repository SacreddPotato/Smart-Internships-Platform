<?php

namespace Tests\Feature;

use App\Enums\InternshipStatus;
use App\Models\CompanyProfile;
use App\Models\Internship;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyInternshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_internships_include_skills_in_a_flat_collection(): void
    {
        $company = CompanyProfile::factory()->create();
        $skill = Skill::factory()->create(['name' => 'React']);
        $internship = Internship::factory()
            ->for($company, 'company')
            ->create(['title' => 'Frontend Intern']);

        $internship->skills()->attach($skill);

        Sanctum::actingAs($company->user);

        $response = $this->getJson('/api/v1/company/internships');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Frontend Intern')
            ->assertJsonPath('data.0.skills.0.name', 'React')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_archived_company_internships_include_skills_in_a_flat_collection(): void
    {
        $company = CompanyProfile::factory()->create();
        $skill = Skill::factory()->create(['name' => 'Laravel']);
        $internship = Internship::factory()
            ->for($company, 'company')
            ->create([
                'title' => 'Backend Intern',
                'status' => InternshipStatus::ARCHIVED,
                'archived_at' => now(),
            ]);

        $internship->skills()->attach($skill);

        Sanctum::actingAs($company->user);

        $response = $this->getJson('/api/v1/company/internships/archived');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Backend Intern')
            ->assertJsonPath('data.0.skills.0.name', 'Laravel')
            ->assertJsonPath('meta.total', 1);
    }
}
