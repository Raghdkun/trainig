<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class QuizController extends Controller
{
    /**
     * Enable a quiz for this station. Idempotent — if a live version already
     * exists, this is a no-op rather than an error. A station whose quiz was
     * removed earlier starts a fresh, empty next version.
     */
    public function store(Section $section): RedirectResponse
    {
        DB::transaction(function () use ($section): void {
            // Lock the station so two quick clicks can't create two live versions.
            Section::whereKey($section->id)->lockForUpdate()->first();

            if ($section->quiz()->exists()) {
                return;
            }

            Quiz::create([
                'section_id' => $section->id,
                'version' => (int) $section->quizVersions()->max('version') + 1,
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quiz added.')]);

        return back();
    }

    /**
     * Remove the quiz from the station. A version that was never sent is
     * deleted outright; one that was sent is retired instead, so its links,
     * answers and scores stay available in Quiz Results.
     */
    public function destroy(Quiz $quiz): RedirectResponse
    {
        if ($quiz->isIssued()) {
            $quiz->update(['retired_at' => now()]);
            $message = __('Quiz removed. Links already sent and their results are kept.');
        } else {
            $quiz->delete();
            $message = __('Quiz removed.');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
