<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Internship;
use App\Models\StudentProfile;
use Database\Seeders\ApplicationSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationSeedingTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_factory_creates_a_valid_pending_application(): void
    {
        $application = Application::factory()->create();

        $this->assertInstanceOf(StudentProfile::class, $application->studentProfile);
        $this->assertInstanceOf(Internship::class, $application->internship);
        $this->assertSame(ApplicationStatus::PENDING, $application->status);
        $this->assertGreaterThanOrEqual(0, $application->match_score);
        $this->assertLessThanOrEqual(100, $application->match_score);
    }

    public function test_application_seeder_creates_applications_for_existing_students_and_internships(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('applications', 16);

        Application::query()
            ->with(['studentProfile', 'internship'])
            ->get()
            ->each(function (Application $application): void {
                $this->assertNotNull($application->studentProfile);
                $this->assertNotNull($application->internship);
                $this->assertContains($application->status, ApplicationStatus::cases());
                $this->assertGreaterThanOrEqual(0, $application->match_score);
                $this->assertLessThanOrEqual(100, $application->match_score);
            });
    }

    public function test_application_seeder_builds_dependencies_when_run_directly(): void
    {
        $this->seed(ApplicationSeeder::class);

        $this->assertDatabaseCount('student_profiles', 8);
        $this->assertDatabaseCount('internships', 15);
        $this->assertDatabaseCount('applications', 16);
    }
}
