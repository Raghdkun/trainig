<?php

namespace Database\Factories;

use App\Enums\QuizQuestionType;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizQuestion>
 */
class QuizQuestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quiz_id' => Quiz::factory(),
            'prompt' => fake()->sentence().'?',
            'type' => QuizQuestionType::Single,
            'order' => 0,
        ];
    }

    /**
     * Attach 4 options (A–D). Single-answer questions (the default) mark
     * only the first correct; pass `correctIndexes` for a multi-answer
     * question (also switches `type` to Multi).
     *
     * @param  array<int, int>|null  $correctIndexes
     */
    public function withOptions(?array $correctIndexes = null): static
    {
        return $this
            ->when($correctIndexes !== null, fn (self $factory) => $factory->state(['type' => QuizQuestionType::Multi]))
            ->afterCreating(function (QuizQuestion $question) use ($correctIndexes): void {
                $correct = $correctIndexes ?? [0];

                foreach (range(0, 3) as $index) {
                    $question->options()->create([
                        'text' => fake()->words(3, true),
                        'is_correct' => in_array($index, $correct, true),
                        'order' => $index,
                    ]);
                }
            });
    }
}
