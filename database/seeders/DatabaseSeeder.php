<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => 'password',
                'role' => UserRole::ADMIN,
            ],
        );

        $this->call([
            SkillSeeder::class,
            StudentProfileSeeder::class,
            InternshipSeeder::class,
        ]);
    }
}
