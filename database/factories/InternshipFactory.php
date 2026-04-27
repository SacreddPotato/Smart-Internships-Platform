<?php

namespace Database\Factories;

use App\Enums\InternshipStatus;
use App\Enums\InternshipType;
use App\Models\CompanyProfile;
use App\Models\Internship;
use Illuminate\Database\Eloquent\Factories\Factory;

class InternshipFactory extends Factory
{
    protected $model = Internship::class;

    public function definition(): array
    {
        return [
            'company_profile_id' => CompanyProfile::factory(),
            'title' => fake()->jobTitle() . ' Intern',
            'description' => fake()->paragraphs(2, true),
            'requirements' => fake()->paragraph(),
            'location' => fake()->city(),
            'type' => fake()->randomElement([
                InternshipType::REMOTE,
                InternshipType::ONSITE,
                InternshipType::HYBRID,
            ]),
            'status' => InternshipStatus::OPEN,
            'starts_at' => now()->addMonth()->toDateString(),
            'ends_at' => now()->addMonths(3)->toDateString(),
            'archived_at' => null,
        ];
    }
}

