<?php

namespace Database\Seeders;

use App\Models\Skill;
use App\Models\StudentProfile;
use Illuminate\Database\Seeder;

class StudentProfileSeeder extends Seeder
{
    public function run(): void
    {
        $skills = Skill::query()->get();

        if ($skills->isEmpty()) {
            $this->call(SkillSeeder::class);
            $skills = Skill::query()->get();
        }

        StudentProfile::factory()
            ->count(8)
            ->create()
            ->each(function (StudentProfile $profile) use ($skills): void {
                $profile->skills()->sync(
                    $skills->random(min(2, $skills->count()))->pluck('id')
                );
            });
    }
}
