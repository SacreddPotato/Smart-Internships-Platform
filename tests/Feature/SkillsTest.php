<?php

namespace Tests\Feature;

use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillsTest extends TestCase
{
    use RefreshDatabase;

    public function test_skills_can_be_listed_for_form_selectors(): void
    {
        Skill::factory()->create(['name' => 'React']);
        Skill::factory()->create(['name' => 'Laravel']);

        $response = $this->getJson('/api/v1/skills');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Laravel')
            ->assertJsonPath('data.1.name', 'React');
    }
}
