<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentProfile>
 */
class StudentProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state([
                'role' => UserRole::STUDENT,
            ]),
            'university' => fake()->randomElement([
                'Cairo University',
                'Ain Shams University',
                'Alexandria University',
                'Mansoura University',
                'German University in Cairo',
            ]),
            'major' => fake()->randomElement([
                'Computer Science',
                'Information Systems',
                'Software Engineering',
                'Computer Engineering',
                'Business Information Systems',
            ]),
            'gpa' => fake()->randomFloat(2, 2.20, 4.00),
            'graduation_year' => fake()->numberBetween((int) now()->format('Y'), (int) now()->format('Y') + 4),
            'bio' => fake()->paragraph(),
            'cv_path' => null,
        ];
    }
}
