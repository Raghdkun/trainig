<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Trainee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class QuizAttemptController extends Controller
{
    /**
     * "Send Quiz" — generate (or, if already sent, just keep) the one-time
     * link for this trainee. The manager copies it and shares it themselves;
     * nothing is emailed automatically.
     */
    public function store(Request $request, Trainee $trainee): RedirectResponse
    {
        $this->authorize('update', $trainee);

        $quiz = Quiz::findOrFail($request->integer('quiz_id'));

        QuizAttempt::firstOrCreate(
            ['quiz_id' => $quiz->id, 'trainee_id' => $trainee->id],
            ['token' => Str::random(48), 'sent_at' => now()],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quiz link ready to share.')]);

        return back();
    }
}
