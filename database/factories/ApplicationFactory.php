<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Internship;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_profile_id' => StudentProfile::factory(),
            'internship_id' => Internship::factory(),
            'status' => ApplicationStatus::PENDING,
            'match_score' => fake()->numberBetween(0, 100),
            'message' => fake()->optional()->sentence(),
            'reviewed_at' => null,
        ];
    }

    public function reviewed(?ApplicationStatus $status = null): static
    {
        return $this->state(fn (): array => [
            'status' => $status ?? fake()->randomElement([
                ApplicationStatus::REVIEWED,
                ApplicationStatus::ACCEPTED,
                ApplicationStatus::REJECTED,
            ]),
            'reviewed_at' => now(),
        ]);
    }
}
