<?php

namespace Database\Seeders;

use App\Models\CompanyProfile;
use App\Models\Internship;
use App\Models\Skill;
use Illuminate\Database\Seeder;

class InternshipSeeder extends Seeder
{
    public function run(): void
    {
        $skills = Skill::query()->get();

        CompanyProfile::factory()
            ->count(5)
            ->create()
            ->each(function (CompanyProfile $company) use ($skills): void {
                Internship::factory()
                    ->count(3)
                    ->for($company, 'company')
                    ->create()
                    ->each(function (Internship $internship) use ($skills): void {
                        $internship->skills()->attach(
                            $skills->random(rand(2, 4))->pluck('id')
                        );
                    });
            });
    }
}
