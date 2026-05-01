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

class CompanyInternshipManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_create_internship_with_skills(): void
    {
        $company = CompanyProfile::factory()->create();
        $react = Skill::factory()->create(['name' => 'React']);
        $laravel = Skill::factory()->create(['name' => 'Laravel']);

        Sanctum::actingAs($company->user);

        $response = $this->postJson('/api/v1/company/internships', [
            'title' => 'Full Stack Intern',
            'description' => 'Build product features.',
            'requirements' => 'Basic web development knowledge.',
            'location' => 'Cairo',
            'type' => InternshipType::HYBRID->value,
            'starts_at' => '2026-06-01',
            'ends_at' => '2026-08-01',
            'skills' => [$react->id, $laravel->id],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.title', 'Full Stack Intern')
            ->assertJsonPath('data.status', InternshipStatus::OPEN->value)
            ->assertJsonPath('data.company.id', $company->id)
            ->assertJsonCount(2, 'data.skills');

        $this->assertDatabaseHas('internships', [
            'company_profile_id' => $company->id,
            'title' => 'Full Stack Intern',
            'status' => InternshipStatus::OPEN->value,
        ]);

        $internshipId = $response->json('data.id');

        $this->assertDatabaseHas('internship_skill', [
            'internship_id' => $internshipId,
            'skill_id' => $react->id,
        ]);
        $this->assertDatabaseHas('internship_skill', [
            'internship_id' => $internshipId,
            'skill_id' => $laravel->id,
        ]);
    }

    public function test_non_company_user_cannot_create_internship(): void
    {
        $student = User::factory()->create(['role' => UserRole::STUDENT]);

        Sanctum::actingAs($student);

        $this->postJson('/api/v1/company/internships', $this->validInternshipPayload())
            ->assertForbidden()
            ->assertJsonPath('message', 'Access denied');
    }

    public function test_company_without_profile_gets_clear_error_when_creating_internship(): void
    {
        $companyUser = User::factory()->create(['role' => UserRole::COMPANY]);

        Sanctum::actingAs($companyUser);

        $this->postJson('/api/v1/company/internships', $this->validInternshipPayload())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'COmpany profile is required before creating an internship');
    }

    public function test_create_validates_required_fields_dates_type_and_skill_ids(): void
    {
        $company = CompanyProfile::factory()->create();

        Sanctum::actingAs($company->user);

        $this->postJson('/api/v1/company/internships', [
            'title' => '',
            'description' => '',
            'type' => 'office',
            'starts_at' => '2026-08-01',
            'ends_at' => '2026-07-01',
            'skills' => [999],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'title',
                'description',
                'type',
                'ends_at',
                'skills.0',
            ]);
    }

    public function test_company_index_returns_only_own_non_archived_internships(): void
    {
        $company = CompanyProfile::factory()->create();
        $otherCompany = CompanyProfile::factory()->create();
        Internship::factory()
            ->for($company, 'company')
            ->create(['title' => 'Own Open', 'status' => InternshipStatus::OPEN]);
        Internship::factory()
            ->for($company, 'company')
            ->create([
                'title' => 'Own Archived',
                'status' => InternshipStatus::ARCHIVED,
                'archived_at' => now(),
            ]);
        Internship::factory()
            ->for($otherCompany, 'company')
            ->create(['title' => 'Other Open', 'status' => InternshipStatus::OPEN]);

        Sanctum::actingAs($company->user);

        $response = $this->getJson('/api/v1/company/internships');
        $titles = collect($response->json('data'))->pluck('title');

        $response->assertOk();
        $this->assertTrue($titles->contains('Own Open'));
        $this->assertFalse($titles->contains('Own Archived'));
        $this->assertFalse($titles->contains('Other Open'));
    }

    public function test_archived_index_returns_only_own_archived_internships(): void
    {
        $company = CompanyProfile::factory()->create();
        $otherCompany = CompanyProfile::factory()->create();
        Internship::factory()
            ->for($company, 'company')
            ->create(['title' => 'Own Open', 'status' => InternshipStatus::OPEN]);
        Internship::factory()
            ->for($company, 'company')
            ->create([
                'title' => 'Own Archived',
                'status' => InternshipStatus::ARCHIVED,
                'archived_at' => now(),
            ]);
        Internship::factory()
            ->for($otherCompany, 'company')
            ->create([
                'title' => 'Other Archived',
                'status' => InternshipStatus::ARCHIVED,
                'archived_at' => now(),
            ]);

        Sanctum::actingAs($company->user);

        $response = $this->getJson('/api/v1/company/internships/archived');
        $titles = collect($response->json('data'))->pluck('title');

        $response->assertOk();
        $this->assertTrue($titles->contains('Own Archived'));
        $this->assertFalse($titles->contains('Own Open'));
        $this->assertFalse($titles->contains('Other Archived'));
    }

    public function test_company_can_update_owned_internship_and_sync_skills(): void
    {
        $company = CompanyProfile::factory()->create();
        $oldSkill = Skill::factory()->create(['name' => 'PHP']);
        $newSkill = Skill::factory()->create(['name' => 'React']);
        $internship = Internship::factory()
            ->for($company, 'company')
            ->create(['title' => 'Old Title']);

        $internship->skills()->attach($oldSkill);

        Sanctum::actingAs($company->user);

        $response = $this->putJson("/api/v1/internships/{$internship->id}", [
            'title' => 'Updated Title',
            'description' => 'Updated description.',
            'requirements' => 'Updated requirements.',
            'location' => 'Alexandria',
            'type' => InternshipType::REMOTE->value,
            'starts_at' => '2026-06-01',
            'ends_at' => '2026-09-01',
            'skills' => [$newSkill->id],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated Title')
            ->assertJsonPath('data.skills.0.name', 'React');

        $this->assertDatabaseHas('internships', [
            'id' => $internship->id,
            'title' => 'Updated Title',
            'location' => 'Alexandria',
        ]);
        $this->assertDatabaseMissing('internship_skill', [
            'internship_id' => $internship->id,
            'skill_id' => $oldSkill->id,
        ]);
        $this->assertDatabaseHas('internship_skill', [
            'internship_id' => $internship->id,
            'skill_id' => $newSkill->id,
        ]);
    }

    public function test_company_cannot_update_archive_or_delete_another_company_internship(): void
    {
        $company = CompanyProfile::factory()->create();
        $otherCompany = CompanyProfile::factory()->create();
        $internship = Internship::factory()
            ->for($otherCompany, 'company')
            ->create(['title' => 'Other Internship']);

        Sanctum::actingAs($company->user);

        $this->putJson("/api/v1/internships/{$internship->id}", $this->validInternshipPayload())
            ->assertForbidden();
        $this->patchJson("/api/v1/internships/{$internship->id}/archive")
            ->assertForbidden();
        $this->deleteJson("/api/v1/internships/{$internship->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('internships', [
            'id' => $internship->id,
            'title' => 'Other Internship',
            'deleted_at' => null,
        ]);
    }

    public function test_company_can_archive_owned_internship(): void
    {
        $company = CompanyProfile::factory()->create();
        $internship = Internship::factory()
            ->for($company, 'company')
            ->create(['status' => InternshipStatus::OPEN]);

        Sanctum::actingAs($company->user);

        $this->patchJson("/api/v1/internships/{$internship->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', InternshipStatus::ARCHIVED->value);

        $internship->refresh();

        $this->assertSame(InternshipStatus::ARCHIVED, $internship->status);
        $this->assertNotNull($internship->archived_at);
    }

    public function test_company_can_soft_delete_owned_internship(): void
    {
        $company = CompanyProfile::factory()->create();
        $internship = Internship::factory()
            ->for($company, 'company')
            ->create();

        Sanctum::actingAs($company->user);

        $this->deleteJson("/api/v1/internships/{$internship->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('internships', [
            'id' => $internship->id,
        ]);
    }

    private function validInternshipPayload(array $overrides = []): array
    {
        return [
            ...[
                'title' => 'Software Intern',
                'description' => 'Build and test application features.',
                'requirements' => 'Basic programming knowledge.',
                'location' => 'Cairo',
                'type' => InternshipType::REMOTE->value,
                'starts_at' => '2026-06-01',
                'ends_at' => '2026-08-01',
                'skills' => [],
            ],
            ...$overrides,
        ];
    }
}
