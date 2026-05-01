<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_reports_api_is_online(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('message', 'API is online');
    }

    public function test_student_can_register_without_being_logged_in_automatically(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Student User',
            'email' => 'student@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => UserRole::STUDENT->value,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'User registered successfully')
            ->assertJsonPath('user.name', 'Student User')
            ->assertJsonPath('user.email', 'student@example.com')
            ->assertJsonPath('user.role', UserRole::STUDENT->value)
            ->assertJsonMissingPath('token');

        $this->assertDatabaseHas('users', [
            'email' => 'student@example.com',
            'role' => UserRole::STUDENT->value,
        ]);
    }

    public function test_company_registration_creates_company_profile(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Acme Company',
            'email' => 'company@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => UserRole::COMPANY->value,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.role', UserRole::COMPANY->value);

        $user = User::query()->where('email', 'company@example.com')->firstOrFail();

        $this->assertDatabaseHas('company_profiles', [
            'user_id' => $user->id,
            'company_name' => 'Acme Company',
        ]);
    }

    public function test_register_validates_role_unique_email_and_password_confirmation(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/v1/register', [
            'name' => '',
            'email' => 'taken@example.com',
            'password' => 'password',
            'password_confirmation' => 'different',
            'role' => 'manager',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
    }

    public function test_user_can_login_fetch_me_and_logout_current_token(): void
    {
        $user = User::factory()->create([
            'email' => 'student@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::STUDENT,
        ]);

        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => 'student@example.com',
            'password' => 'password',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('message', 'User logged in successfully')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.role', UserRole::STUDENT->value)
            ->assertJsonStructure(['token']);

        $plainTextToken = $loginResponse->json('token');

        $this->withToken($plainTextToken)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'student@example.com');

        $this->assertSame(1, PersonalAccessToken::query()->count());

        $this->withToken($plainTextToken)
            ->postJson('/api/v1/logout')
            ->assertOk()
            ->assertJsonPath('message', 'User logged out successfully');

        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'student@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::STUDENT,
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'student@example.com',
            'password' => 'wrong-password',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid credentials');
    }

    public function test_protected_and_role_routes_reject_missing_or_wrong_user(): void
    {
        $student = User::factory()->create(['role' => UserRole::STUDENT]);

        $this->getJson('/api/v1/me')
            ->assertUnauthorized();

        Sanctum::actingAs($student);

        $this->getJson('/api/v1/company/internships')
            ->assertForbidden()
            ->assertJsonPath('message', 'Access denied');
    }
}
