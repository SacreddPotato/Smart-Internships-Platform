<?php

namespace Database\Seeders;
use App\Models\Skill;
use Illuminate\Database\Seeder;

class SkillSeeder extends Seeder
{
    public function run(): void
    {
        collect([
            'Laravel',
            'PHP',
            'React',
            'JavaScript',
            'SQL',
            'Git',
            'HTML',
            'CSS',
            'Python',
            'Communication',
        ])->each(fn ($name) => Skill::query()->firstOrCreate(['name' => $name]));
    }
}
