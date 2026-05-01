<?php

namespace Database\Seeders;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Internship;
use App\Models\StudentProfile;
use Illuminate\Database\Seeder;

class ApplicationSeeder extends Seeder
{
    public function run(): void
    {
        if (StudentProfile::query()->doesntExist()) {
            $this->call(StudentProfileSeeder::class);
        }

        if (Internship::query()->doesntExist()) {
            $this->call(InternshipSeeder::class);
        }

        $internships = Internship::query()->get();
        $statuses = ApplicationStatus::cases();

        StudentProfile::query()
            ->take(8)
            ->get()
            ->each(function (StudentProfile $profile, int $studentIndex) use ($internships, $statuses): void {
                collect([
                    $internships[$studentIndex % $internships->count()],
                    $internships[($studentIndex + 8) % $internships->count()],
                ])
                    ->each(function (Internship $internship, int $internshipIndex) use ($profile, $statuses): void {
                        $status = $statuses[($profile->id + $internshipIndex) % count($statuses)];

                        Application::query()->firstOrCreate(
                            [
                                'student_profile_id' => $profile->id,
                                'internship_id' => $internship->id,
                            ],
                            [
                                'status' => $status,
                                'match_score' => fake()->numberBetween(45, 98),
                                'message' => fake()->optional()->sentence(),
                                'reviewed_at' => $status === ApplicationStatus::PENDING ? null : now(),
                            ],
                        );
                    });
            });
    }
}
