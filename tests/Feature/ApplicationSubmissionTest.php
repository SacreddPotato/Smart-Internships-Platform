<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Internship;
use App\Models\StudentProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApplicationSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_gets_clear_error_when_applying_to_same_internship_twice(): void
    {
        $profile = StudentProfile::factory()->create();
        $internship = Internship::factory()->create();

        Application::factory()
            ->for($profile, 'studentProfile')
            ->for($internship)
            ->create();

        Sanctum::actingAs($profile->user);

        $this->postJson("/api/v1/internships/{$internship->id}/applications")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'You have already applied to this internship.');

        $this->assertDatabaseCount('applications', 1);
    }
}
