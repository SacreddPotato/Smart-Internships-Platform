<?php

namespace Tests\Feature;

use App\Enums\InternshipType;
use App\Models\CompanyProfile;
use App\Models\Internship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InternshipValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_update_internship_when_end_date_matches_start_date(): void
    {
        $company = CompanyProfile::factory()->create();
        $internship = Internship::factory()
            ->for($company, 'company')
            ->create();

        Sanctum::actingAs($company->user);

        $response = $this->putJson("/api/v1/internships/{$internship->id}", [
            'title' => 'Updated Internship',
            'description' => 'Updated internship description.',
            'requirements' => 'Updated requirements.',
            'location' => 'Remote',
            'type' => InternshipType::REMOTE->value,
            'starts_at' => '2026-06-01',
            'ends_at' => '2026-06-01',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated Internship')
            ->assertJsonPath('data.starts_at', '2026-06-01')
            ->assertJsonPath('data.ends_at', '2026-06-01');

        $internship->refresh();

        $this->assertSame('Updated Internship', $internship->title);
        $this->assertSame('2026-06-01', $internship->starts_at->toDateString());
        $this->assertSame('2026-06-01', $internship->ends_at->toDateString());
    }
}
