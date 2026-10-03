<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\QuizQuestionOption;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class QuizResultController extends Controller
{
    /**
     * Every quiz attempt across the program — sent and completed — for the
     * training team to track and follow up on. Kept out of the Manager's
     * reach entirely (super-admin-only route group), unlike the trainee
     * page's link/status, which any assigned manager can see.
     */
    public function index(): Response
    {
        $attempts = QuizAttempt::query()
            ->with(['trainee:id,name,store_id', 'trainee.store:id,name', 'quiz.section:id,title'])
            ->orderByDesc('sent_at')
            ->get();

        return Inertia::render('training/quiz-results/index', [
            'attempts' => $attempts->map(fn (QuizAttempt $attempt): array => [
                'id' => $attempt->id,
                'trainee' => $attempt->trainee->only(['id', 'name']),
                'store' => $attempt->trainee->store->only(['id', 'name']),
                'section' => $attempt->quiz->section->only(['id', 'title']),
                'version' => $attempt->quiz->version,
                'status' => $attempt->status()->value,
                'flagged' => $attempt->isFlaggedAsMisdirected(),
                'score' => $attempt->score,
                'link' => $attempt->isCompleted() ? null : route('quiz.show', $attempt->token),
                'sent_at' => $attempt->sent_at->toIso8601String(),
                'started_at' => $attempt->started_at?->toIso8601String(),
                'completed_at' => $attempt->completed_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * One attempt's full question-by-question breakdown — answers and score
     * live only here.
     */
    public function show(QuizAttempt $attempt): Response
    {
        $attempt->load([
            'trainee:id,name,store_id',
            'trainee.store:id,name',
            'quiz.section:id,title',
            'quiz.questions.options',
            'answers',
        ]);

        $answersByQuestion = $attempt->answers->groupBy('quiz_question_id');

        $questions = $attempt->quiz->questions->map(function (QuizQuestion $question) use ($answersByQuestion): array {
            $chosenOptionIds = $answersByQuestion->get($question->id, collect())->pluck('quiz_question_option_id');
            $correctOptionIds = $question->options->where('is_correct', true)->pluck('id');

            return [
                'id' => $question->id,
                'prompt' => $question->prompt,
                'type' => $question->type->value,
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

        return Inertia::render('training/quiz-results/show', [
            'attempt' => [
                'id' => $attempt->id,
                'trainee' => $attempt->trainee->only(['id', 'name']),
                'store' => $attempt->trainee->store->only(['id', 'name']),
                'section' => $attempt->quiz->section->only(['id', 'title']),
                'version' => $attempt->quiz->version,
                'status' => $attempt->status()->value,
                'flagged' => $attempt->isFlaggedAsMisdirected(),
                'score' => $attempt->score,
                'correct_count' => $attempt->isCompleted() ? $questions->where('is_correct', true)->count() : null,
                'questions_count' => $questions->count(),
                'link' => $attempt->isCompleted() ? null : route('quiz.show', $attempt->token),
                'sent_at' => $attempt->sent_at->toIso8601String(),
                'started_at' => $attempt->started_at?->toIso8601String(),
                'completed_at' => $attempt->completed_at?->toIso8601String(),
            ],
            'questions' => $questions,
        ]);
    }

    /**
     * "Reset" — delete the attempt (and its answers) so the station can be
     * sent to this trainee again.
     */
    public function destroy(QuizAttempt $attempt): RedirectResponse
    {
        $attempt->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quiz attempt reset.')]);

        return to_route('training.quiz-results.index');
    }
}
