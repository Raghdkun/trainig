<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Trainee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class QuizAttemptController extends Controller
{
    /** Mirrors the builder's minimum — a quiz can't be sent with fewer. */
    private const MIN_QUESTIONS_TO_SEND = 3;

    /**
     * "Send Quiz" — generate (or, if already sent, just keep) the one-time
     * link for this trainee. Only those allowed to share quiz links (super
     * admins) may do this; they copy it and share it themselves — nothing is
     * emailed automatically.
     */
    public function store(Request $request, Trainee $trainee): RedirectResponse
    {
        $this->authorize('shareQuizLink', $trainee);

        if ($trainee->isArchived()) {
            throw ValidationException::withMessages([
                'quiz_id' => __('Restore this trainee before sending a quiz.'),
            ]);
        }

        // Same row lock ReviseQuiz takes, so a version can't be edited in
        // place while its first link is being created.
        DB::transaction(function () use ($request, $trainee): void {
            $quiz = Quiz::withCount('questions')->lockForUpdate()->findOrFail($request->integer('quiz_id'));

            // Links always go to the live version; older ones are frozen history.
            if ($quiz->isRetired()) {
                throw ValidationException::withMessages([
                    'quiz_id' => __('This quiz has been updated. Refresh the page to send the latest version.'),
                ]);
            }

            if ($quiz->questions_count < self::MIN_QUESTIONS_TO_SEND) {
                throw ValidationException::withMessages([
                    'quiz_id' => __('A quiz needs at least :min questions before it can be sent.', ['min' => self::MIN_QUESTIONS_TO_SEND]),
                ]);
            }

            // One link per version per trainee: re-sending the same version
            // keeps the link already shared; a new version gets its own.
            QuizAttempt::firstOrCreate(
                ['quiz_id' => $quiz->id, 'trainee_id' => $trainee->id],
                ['token' => Str::random(48), 'sent_at' => now()],
            );
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quiz link ready to share.')]);

        return back();
    }
}
