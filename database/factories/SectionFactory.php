<?php

namespace Database\Factories;

use App\Enums\SectionStatus;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'icon' => 'ClipboardList',
            'order' => 0,
            'status' => SectionStatus::Published,
            'pie_content_review' => fake()->randomElement(['5 to 10 Mins', '10 to 15 Mins']),
            'screen_to_shoulder' => fake()->randomElement(['20 Mins', '30 Mins', '60 Mins']),
            'hands_on_shifts' => fake()->randomElement(['1 hour', '2 hours', '5 hours']),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => SectionStatus::Draft]);
    }

    public function published(): static
    {
        return $this->state(['status' => SectionStatus::Published]);
    }
}
