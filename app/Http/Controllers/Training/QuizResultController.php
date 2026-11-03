<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\QuizAttempt;
use App\Models\Store;
use App\Services\Training\QuizAttemptBreakdown;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuizResultController extends Controller
{
    /**
     * Every quiz attempt across the program — sent and completed — for the
     * training team to track and follow up on. Kept out of the Manager's
     * reach entirely (super-admin-only route group), unlike the trainee
     * page's link/status, which any assigned manager can see.
     *
     * Follows the shared store filter (`?store=`) so results can be reviewed
     * store by store; the quiz, result and status filters run client-side.
     */
    public function index(Request $request): Response
    {
        $storeId = $request->user()->resolveStoreFilter($request->integer('store') ?: null);

        $attempts = QuizAttempt::query()
            ->when($storeId, fn ($query) => $query->whereHas('trainee', fn ($trainee) => $trainee->inStore($storeId)))
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
            'stores' => Store::orderBy('name')->get(['id', 'name']),
            'filters' => ['store' => $storeId],
        ]);
    }

    /**
     * One attempt's full question-by-question breakdown — answers and score
     * live only here.
     */
    public function show(QuizAttempt $attempt, QuizAttemptBreakdown $breakdown): Response
    {
        $attempt->load([
            'trainee:id,name,store_id',
            'trainee.store:id,name',
            'quiz.section:id,title',
            'quiz.questions.options',
            'answers',
        ]);

        $questions = $breakdown->questions($attempt);

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
                'results_reviewed_at' => $attempt->results_reviewed_at?->toIso8601String(),
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
