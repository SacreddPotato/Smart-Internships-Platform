<?php

namespace Tests\Feature;

use App\Enums\InternshipStatus;
use App\Enums\InternshipType;
use App\Enums\UserRole;
use App\Models\CompanyProfile;
use App\Models\Internship;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BrowseInternshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_browse_requires_authentication(): void
    {
        $this->getJson('/api/v1/internships')
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_browse_open_internships_with_company_skills_and_pagination(): void
    {
        $student = User::factory()->create(['role' => UserRole::STUDENT]);
        $company = CompanyProfile::factory()->create(['company_name' => 'Acme']);
        $skill = Skill::factory()->create(['name' => 'React']);
        $openInternship = Internship::factory()
            ->for($company, 'company')
            ->create([
                'title' => 'Frontend Intern',
                'status' => InternshipStatus::OPEN,
            ]);

        $openInternship->skills()->attach($skill);

        Internship::factory()
            ->for($company, 'company')
            ->create([
                'title' => 'Archived Intern',
                'status' => InternshipStatus::ARCHIVED,
                'archived_at' => now(),
            ]);

        Internship::factory()
            ->for($company, 'company')
            ->create([
                'title' => 'Closed Intern',
                'status' => InternshipStatus::CLOSED,
            ]);

        Sanctum::actingAs($student);

        $response = $this->getJson('/api/v1/internships');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Frontend Intern')
            ->assertJsonPath('data.0.status', InternshipStatus::OPEN->value)
            ->assertJsonPath('data.0.company.company_name', 'Acme')
            ->assertJsonPath('data.0.skills.0.name', 'React')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonMissingPath('data.1');
    }

    public function test_browse_filters_by_type(): void
    {
        $student = User::factory()->create(['role' => UserRole::STUDENT]);

        Internship::factory()->create([
            'title' => 'Remote Intern',
            'type' => InternshipType::REMOTE,
        ]);
        Internship::factory()->create([
            'title' => 'Onsite Intern',
            'type' => InternshipType::ONSITE,
        ]);

        Sanctum::actingAs($student);

        $this->getJson('/api/v1/internships?type=remote')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Remote Intern')
            ->assertJsonPath('data.0.type', InternshipType::REMOTE->value);
    }

    public function test_browse_searches_terms_with_any_match_across_title_description_company_and_skills(): void
    {
        $student = User::factory()->create(['role' => UserRole::STUDENT]);
        $react = Skill::factory()->create(['name' => 'React']);
        $sql = Skill::factory()->create(['name' => 'SQL']);
        $acme = CompanyProfile::factory()->create(['company_name' => 'Acme Labs']);
        $otherCompany = CompanyProfile::factory()->create(['company_name' => 'Other Company']);

        $frontend = Internship::factory()
            ->for($otherCompany, 'company')
            ->create(['title' => 'Frontend Intern']);
        $frontend->skills()->attach($react);

        $database = Internship::factory()
            ->for($otherCompany, 'company')
            ->create([
                'title' => 'Database Intern',
                'description' => 'Build reporting dashboards',
            ]);
        $database->skills()->attach($sql);

        Internship::factory()
            ->for($acme, 'company')
            ->create(['title' => 'Operations Intern']);

        Internship::factory()
            ->for($otherCompany, 'company')
            ->create(['title' => 'Unmatched Intern']);

        Sanctum::actingAs($student);

        $response = $this->getJson('/api/v1/internships?terms[]=React&terms[]=reporting&terms[]=Acme');

        $titles = collect($response->json('data'))->pluck('title');

        $response->assertOk();
        $this->assertTrue($titles->contains('Frontend Intern'));
        $this->assertTrue($titles->contains('Database Intern'));
        $this->assertTrue($titles->contains('Operations Intern'));
        $this->assertFalse($titles->contains('Unmatched Intern'));
    }

    public function test_browse_search_can_require_all_terms(): void
    {
        $student = User::factory()->create(['role' => UserRole::STUDENT]);
        $react = Skill::factory()->create(['name' => 'React']);

        $matching = Internship::factory()->create([
            'title' => 'Frontend Intern',
            'description' => 'Build accessible interfaces',
        ]);
        $matching->skills()->attach($react);

        $partial = Internship::factory()->create([
            'title' => 'Frontend Intern Without Skill',
            'description' => 'Build accessible interfaces',
        ]);

        Sanctum::actingAs($student);

        $response = $this->getJson('/api/v1/internships?match=all&terms[]=Frontend&terms[]=React');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id);

        $this->assertNotSame($partial->id, $response->json('data.0.id'));
    }

    public function test_show_returns_one_internship_with_company_and_skills(): void
    {
        $student = User::factory()->create(['role' => UserRole::STUDENT]);
        $company = CompanyProfile::factory()->create(['company_name' => 'Acme']);
        $skill = Skill::factory()->create(['name' => 'Laravel']);
        $internship = Internship::factory()
            ->for($company, 'company')
            ->create(['title' => 'Backend Intern']);

        $internship->skills()->attach($skill);

        Sanctum::actingAs($student);

        $this->getJson("/api/v1/internships/{$internship->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Backend Intern')
            ->assertJsonPath('data.company.company_name', 'Acme')
            ->assertJsonPath('data.skills.0.name', 'Laravel');
    }
}
