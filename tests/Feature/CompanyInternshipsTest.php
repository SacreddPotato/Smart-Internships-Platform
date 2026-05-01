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

    public function test_company_internships_return_complete_pagination_information(): void
    {
        $company = CompanyProfile::factory()->create();

        Internship::factory()
            ->count(13)
            ->for($company, 'company')
            ->create();

        Sanctum::actingAs($company->user);

        $response = $this->getJson('/api/v1/company/internships');

        $response
            ->assertOk()
            ->assertJsonCount(12, 'data')
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'path',
                    'per_page',
                    'to',
                    'total',
                ],
            ])
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.from', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 12)
            ->assertJsonPath('meta.to', 12)
            ->assertJsonPath('meta.total', 13);
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
