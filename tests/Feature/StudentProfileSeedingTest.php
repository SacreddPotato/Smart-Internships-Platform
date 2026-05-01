<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Skill;
use App\Models\StudentProfile;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentProfileSeedingTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_profile_factory_creates_a_student_user(): void
    {
        $profile = StudentProfile::factory()->create();

        $this->assertSame(UserRole::STUDENT, $profile->user->role);
        $this->assertNotNull($profile->university);
        $this->assertNotNull($profile->major);
        $this->assertIsInt($profile->graduation_year);
    }

    public function test_database_seeder_creates_student_profiles_with_skills(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('skills', 10);
        $this->assertDatabaseCount('student_profiles', 8);

        StudentProfile::query()
            ->with(['user', 'skills'])
            ->get()
            ->each(function (StudentProfile $profile): void {
                $this->assertSame(UserRole::STUDENT, $profile->user->role);
                $this->assertGreaterThanOrEqual(2, $profile->skills->count());
                $this->assertLessThanOrEqual(4, $profile->skills->count());
            });
    }

    public function test_student_profile_seeder_uses_existing_skills(): void
    {
        Skill::factory()->count(4)->create();

        $this->seed(\Database\Seeders\StudentProfileSeeder::class);

        $this->assertDatabaseCount('student_profiles', 8);
        $this->assertDatabaseCount('student_skill', 8 * 2);
    }
}
