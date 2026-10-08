<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Http\Requests\Training\AssessmentQuestionRequest;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentSkill;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Development Zone assessment questions, authored per station/skill on the
 * Assessment setup page (super-admin-only route group).
 */
class AssessmentQuestionController extends Controller
{
    public function store(AssessmentQuestionRequest $request, AssessmentSkill $skill): RedirectResponse
    {
        $skill->questions()->create([
            'prompt' => $request->validated('prompt'),
            'answer_type' => $request->validated('answer_type'),
            'order' => (int) $skill->questions()->max('order') + 1,
        ]);

        return back();
    }

    public function update(AssessmentQuestionRequest $request, AssessmentQuestion $question): RedirectResponse
    {
        $question->update($request->validated());

        return back();
    }

    /**
     * A question nobody has answered yet is deleted; one already used in an
     * assessment is retired instead, so past answers stay intact. Either way
     * it's no longer asked.
     */
    public function destroy(AssessmentQuestion $question): RedirectResponse
    {
        if ($question->answers()->exists()) {
            $question->update(['is_active' => false]);
        } else {
            $question->delete();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assessment question removed.')]);

        return back();
    }
}
