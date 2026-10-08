<?php

namespace App\Http\Controllers\Training;

use App\Enums\AssessmentAnswerType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Training\AssessmentSkillRequest;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentSkill;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Assessment setup — the stations & skills the Development Zone assessment
 * rates, each with its own questions (super-admin-only route group).
 */
class AssessmentSkillController extends Controller
{
    public function index(): Response
    {
        $skills = AssessmentSkill::ordered()
            ->with(['section:id,title', 'questions' => fn ($query) => $query->active()])
            ->get();

        return Inertia::render('training/development-zone/assessment-setup', [
            'skills' => $skills->map(fn (AssessmentSkill $skill): array => [
                'id' => $skill->id,
                'name' => $skill->name,
                'description' => $skill->description,
                'section' => $skill->section?->only(['id', 'title']),
                'is_active' => $skill->is_active,
                'questions' => $skill->questions->map(fn (AssessmentQuestion $question): array => [
                    'id' => $question->id,
                    'prompt' => $question->prompt,
                    'answer_type' => $question->answer_type->value,
                ])->values(),
            ])->values(),
            'sectionOptions' => Section::ordered()->get(['id', 'title']),
            'answerTypeOptions' => AssessmentAnswerType::options(),
        ]);
    }

    public function store(AssessmentSkillRequest $request): RedirectResponse
    {
        AssessmentSkill::create([
            ...$request->safe()->only(['name', 'description', 'section_id']),
            'order' => (int) AssessmentSkill::max('order') + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Station / skill added. Now add its questions.')]);

        return back();
    }

    public function update(AssessmentSkillRequest $request, AssessmentSkill $skill): RedirectResponse
    {
        $skill->update($request->validated());

        return back();
    }

    /**
     * One that's never been assessed is deleted with its questions; one with
     * past answers is deactivated instead, so history stays intact.
     */
    public function destroy(AssessmentSkill $skill): RedirectResponse
    {
        if ($skill->hasBeenAssessed()) {
            $skill->update(['is_active' => false]);
            $message = __('Already used in assessments, so it was deactivated instead of deleted.');
        } else {
            $skill->delete();
            $message = __('Station / skill removed.');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
