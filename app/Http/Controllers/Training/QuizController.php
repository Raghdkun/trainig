<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class QuizController extends Controller
{
    /**
     * Enable a quiz for this station. Idempotent — if one already exists,
     * this is a no-op rather than an error.
     */
    public function store(Section $section): RedirectResponse
    {
        Quiz::firstOrCreate(['section_id' => $section->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quiz added.')]);

        return back();
    }

    /**
     * Remove the quiz entirely — its questions and any in-progress or
     * completed attempts go with it (the builder's dialog warns about this
     * before calling here).
     */
    public function destroy(Quiz $quiz): RedirectResponse
    {
        $quiz->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quiz removed.')]);

        return back();
    }
}
