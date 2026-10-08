<?php

namespace Database\Factories;

use App\Models\AssessmentSkill;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentSkill>
 */
class AssessmentSkillFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => null,
            'section_id' => null,
            'order' => 0,
            'is_active' => true,
        ];
    }

    /**
     * Linked to a Content Builder station for plan suggestions.
     */
    public function linkedTo(?Section $section = null): static
    {
        return $this->state(fn (): array => [
            'section_id' => $section?->id ?? Section::factory(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
