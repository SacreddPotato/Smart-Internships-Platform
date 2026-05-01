<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CompanyProfile;
use App\Models\Skill;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_profile_requires_authentication_and_student_role(): void
    {
        $this->getJson('/api/v1/student/profile')
            ->assertUnauthorized();

        $company = CompanyProfile::factory()->create();

        Sanctum::actingAs($company->user);

        $this->getJson('/api/v1/student/profile')
            ->assertForbidden()
            ->assertJsonPath('message', 'Access denied');
    }

    public function test_student_can_fetch_profile_and_empty_profile_is_created_if_missing(): void
    {
        $student = User::factory()->create([
            'name' => 'Student User',
            'email' => 'student@example.com',
            'role' => UserRole::STUDENT,
        ]);

        Sanctum::actingAs($student);

        $response = $this->getJson('/api/v1/student/profile');

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Student User')
            ->assertJsonPath('data.email', 'student@example.com')
            ->assertJsonPath('data.university', null)
            ->assertJsonPath('data.skills', []);

        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $student->id,
        ]);
    }

    public function test_student_can_update_profile_fields(): void
    {
        $profile = StudentProfile::factory()->create();

        Sanctum::actingAs($profile->user);

        $response = $this->putJson('/api/v1/student/profile', [
            'university' => 'Cairo University',
            'major' => 'Computer Science',
            'gpa' => 3.75,
            'graduation_year' => 2027,
            'bio' => 'Interested in backend APIs.',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.university', 'Cairo University')
            ->assertJsonPath('data.major', 'Computer Science')
            ->assertJsonPath('data.gpa', 3.75)
            ->assertJsonPath('data.graduation_year', 2027)
            ->assertJsonPath('data.bio', 'Interested in backend APIs.');

        $this->assertDatabaseHas('student_profiles', [
            'id' => $profile->id,
            'university' => 'Cairo University',
            'major' => 'Computer Science',
            'graduation_year' => 2027,
        ]);
    }

    public function test_profile_update_validates_fields(): void
    {
        $profile = StudentProfile::factory()->create();

        Sanctum::actingAs($profile->user);

        $this->putJson('/api/v1/student/profile', [
            'university' => str_repeat('a', 256),
            'major' => str_repeat('b', 256),
            'gpa' => 5,
            'graduation_year' => 2041,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'university',
                'major',
                'gpa',
                'graduation_year',
            ]);
    }

    public function test_student_can_sync_and_replace_skills(): void
    {
        $profile = StudentProfile::factory()->create();
        $php = Skill::factory()->create(['name' => 'PHP']);
        $react = Skill::factory()->create(['name' => 'React']);
        $sql = Skill::factory()->create(['name' => 'SQL']);

        $profile->skills()->attach($php);

        Sanctum::actingAs($profile->user);

        $response = $this->putJson('/api/v1/student/skills', [
            'skills' => [$react->id, $sql->id],
        ]);

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data.skills')
            ->assertJsonPath('data.skills.0.name', 'React')
            ->assertJsonPath('data.skills.1.name', 'SQL');

        $this->assertDatabaseMissing('student_skill', [
            'student_profile_id' => $profile->id,
            'skill_id' => $php->id,
        ]);
        $this->assertDatabaseHas('student_skill', [
            'student_profile_id' => $profile->id,
            'skill_id' => $react->id,
        ]);
        $this->assertDatabaseHas('student_skill', [
            'student_profile_id' => $profile->id,
            'skill_id' => $sql->id,
        ]);
    }

    public function test_skill_sync_validates_skill_ids(): void
    {
        $profile = StudentProfile::factory()->create();

        Sanctum::actingAs($profile->user);

        $this->putJson('/api/v1/student/skills', [
            'skills' => [999],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['skills.0']);
    }

    public function test_student_can_upload_cv_and_old_cv_is_replaced(): void
    {
        Storage::fake('public');

        $profile = StudentProfile::factory()->create([
            'cv_path' => UploadedFile::fake()->create('old.pdf', 10, 'application/pdf')->store('cvs', 'public'),
        ]);
        $oldPath = $profile->cv_path;

        Sanctum::actingAs($profile->user);

        $response = $this->postJson('/api/v1/student/profile/cv', [
            'cv' => UploadedFile::fake()->create('new.pdf', 100, 'application/pdf'),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.cv_path', fn ($path) => is_string($path) && str_starts_with($path, 'cvs/'))
            ->assertJsonPath('data.cv_url', fn ($url) => is_string($url) && str_contains($url, '/storage/cvs/'));

        $profile->refresh();

        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($profile->cv_path);
    }

    public function test_cv_upload_validates_file_type_and_size(): void
    {
        Storage::fake('public');

        $profile = StudentProfile::factory()->create();

        Sanctum::actingAs($profile->user);

        $this->postJson('/api/v1/student/profile/cv', [
            'cv' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cv']);
    }
}
