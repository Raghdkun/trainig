<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\QuizAttempt;
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
                'status' => $attempt->isCompleted() ? 'completed' : 'sent',
                'flagged' => $attempt->isFlaggedAsMisdirected(),
                'score' => $attempt->score,
                'sent_at' => $attempt->sent_at->toIso8601String(),
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
            'trainee:id,name',
            'quiz.section:id,title',
            'quiz.questions.options',
            'answers',
        ]);

        $answersByQuestion = $attempt->answers->groupBy('quiz_question_id');

        return Inertia::render('training/quiz-results/show', [
            'attempt' => [
                'id' => $attempt->id,
                'trainee' => $attempt->trainee->only(['id', 'name']),
                'section' => $attempt->quiz->section->only(['id', 'title']),
                'status' => $attempt->isCompleted() ? 'completed' : 'sent',
                'score' => $attempt->score,
                'sent_at' => $attempt->sent_at->toIso8601String(),
                'completed_at' => $attempt->completed_at?->toIso8601String(),
            ],
            'questions' => $attempt->quiz->questions->map(function ($question) use ($answersByQuestion): array {
                $chosenOptionIds = $answersByQuestion->get($question->id, collect())->pluck('quiz_question_option_id');

                return [
                    'id' => $question->id,
                    'prompt' => $question->prompt,
                    'type' => $question->type->value,
                    'options' => $question->options->map(fn ($option): array => [
                        'id' => $option->id,
                        'text' => $option->text,
                        'is_correct' => $option->is_correct,
                        'is_chosen' => $chosenOptionIds->contains($option->id),
                    ])->values(),
                ];
            })->values(),
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
