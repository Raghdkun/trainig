<?php

namespace Database\Factories;

use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quiz>
 */
class QuizFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'section_id' => Section::factory(),
        ];
    }

    /**
     * Build a quiz with `$count` questions, each with 4 options and one
     * marked correct — a fully realistic quiz ready to send.
     */
    public function withQuestions(int $count = 3): static
    {
        return $this->afterCreating(function (Quiz $quiz) use ($count): void {
            QuizQuestion::factory()
                ->count($count)
                ->withOptions()
                ->create(['quiz_id' => $quiz->id]);
        });
    }
}
