<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyProfileFactory extends Factory
{
    protected $model = CompanyProfile::class;

    public function definition(): array
    {
        $companyName = fake()->company();

        return [
            'user_id' => User::factory()->state([
                'role' => UserRole::COMPANY,
                'name' => $companyName,
            ]),
            'company_name' => $companyName,
            'industry' => fake()->randomElement(['Technology', 'Finance', 'Education', 'Healthcare']),
            'website' => fake()->url(),
            'description' => fake()->paragraph(),
        ];
    }
}
