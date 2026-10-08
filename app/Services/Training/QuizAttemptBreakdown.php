<?php

namespace App\Services\Training;

use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\QuizQuestionOption;
use Illuminate\Support\Collection;

/**
 * Question-by-question view of an attempt — what was chosen, what was
 * correct, and why. Shared by the training team's result page and the
 * trainee's own result screen so both always agree.
 */
class QuizAttemptBreakdown
{
    /**
     * @return Collection<int, array{id: int, prompt: string, type: string, explanation: string|null, is_correct: bool, options: Collection<int, array{id: int, text: string, is_correct: bool, is_chosen: bool}>}>
     */
    public function questions(QuizAttempt $attempt): Collection
    {
        $attempt->loadMissing(['quiz.questions.options', 'answers']);

        $answersByQuestion = $attempt->answers->groupBy('quiz_question_id');

        return $attempt->quiz->questions->map(function (QuizQuestion $question) use ($answersByQuestion): array {
            $chosenOptionIds = $answersByQuestion->get($question->id, collect())->pluck('quiz_question_option_id');
            $correctOptionIds = $question->options->where('is_correct', true)->pluck('id');

            return [
                'id' => $question->id,
                'prompt' => $question->prompt,
                'type' => $question->type->value,
                'explanation' => $question->explanation,
                // Same exact-match rule the score was calculated with.
                'is_correct' => $chosenOptionIds->sort()->values()->all() === $correctOptionIds->sort()->values()->all(),
                'options' => $question->options->map(fn (QuizQuestionOption $option): array => [
                    'id' => $option->id,
                    'text' => $option->text,
                    'is_correct' => $option->is_correct,
                    'is_chosen' => $chosenOptionIds->contains($option->id),
                ])->values(),
            ];
        })->values();
    }
}
