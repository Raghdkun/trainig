<?php

namespace Database\Factories;

use App\Enums\AssessmentAnswerType;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentSkill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentQuestion>
 */
class AssessmentQuestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_skill_id' => AssessmentSkill::factory(),
            'prompt' => fake()->sentence().'?',
            'answer_type' => AssessmentAnswerType::YesNo,
            'order' => 0,
            'is_active' => true,
        ];
    }

    public function level(): static
    {
        return $this->state(fn (): array => ['answer_type' => AssessmentAnswerType::Level]);
    }

    public function percentage(): static
    {
        return $this->state(fn (): array => ['answer_type' => AssessmentAnswerType::Percentage]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
