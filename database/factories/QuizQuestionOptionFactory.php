<?php

namespace Database\Factories;

use App\Models\QuizQuestion;
use App\Models\QuizQuestionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizQuestionOption>
 */
class QuizQuestionOptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quiz_question_id' => QuizQuestion::factory(),
            'text' => fake()->words(3, true),
            'is_correct' => false,
            'order' => 0,
        ];
    }

    public function correct(): static
    {
        return $this->state(['is_correct' => true]);
    }
}
