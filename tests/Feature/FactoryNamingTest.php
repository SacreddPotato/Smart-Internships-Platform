<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\StudentProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FactoryNamingTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_profile_factory_creates_plain_student_names(): void
    {
        $profiles = StudentProfile::factory()->count(20)->create();

        foreach ($profiles as $profile) {
            $this->assertDoesNotMatchRegularExpression(
                '/^(Dr\.|Mr\.|Mrs\.|Ms\.|Miss)\s/',
                $profile->user->name,
            );
        }
    }

    public function test_company_profile_factory_names_the_user_after_the_company(): void
    {
        $profile = CompanyProfile::factory()->create();

        $this->assertSame($profile->company_name, $profile->user->name);
    }
}
