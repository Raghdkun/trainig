<?php

namespace Database\Factories;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Trainee;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<QuizAttempt>
 */
class QuizAttemptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quiz_id' => Quiz::factory(),
            'trainee_id' => Trainee::factory(),
            'token' => Str::random(48),
            'sent_at' => now(),
            'completed_at' => null,
            'score' => null,
        ];
    }

    public function completed(int $score = 100): static
    {
        return $this->state([
            'completed_at' => now(),
            'score' => $score,
        ]);
    }
}
