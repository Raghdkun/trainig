<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDevelopmentEvaluationCriterionRequest;
use App\Http\Requests\Admin\UpdateDevelopmentEvaluationCriterionRequest;
use App\Models\DevelopmentEvaluationCriterion;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class DevelopmentEvaluationCriterionController extends Controller
{
    public function store(StoreDevelopmentEvaluationCriterionRequest $request): RedirectResponse
    {
        $this->authorize('create', DevelopmentEvaluationCriterion::class);

        DevelopmentEvaluationCriterion::create([
            'label' => $request->validated('label'),
            'description' => $request->validated('description'),
            'order' => $request->validated('order') ?? DevelopmentEvaluationCriterion::max('order') + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Evaluation question added.')]);

        return back();
    }

    public function update(UpdateDevelopmentEvaluationCriterionRequest $request, DevelopmentEvaluationCriterion $criterion): RedirectResponse
    {
        $this->authorize('update', $criterion);

        $criterion->update([
            'label' => $request->validated('label'),
            'description' => $request->validated('description'),
            'order' => $request->validated('order') ?? $criterion->order,
            'is_active' => $request->validated('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Evaluation question updated.')]);

        return back();
    }

    /**
     * A question that has already been used in an evaluation can't be
     * hard-deleted (its rating rows would lose a valid criterion) — the
     * admin is directed to deactivate it instead so history stays intact.
     */
    public function destroy(DevelopmentEvaluationCriterion $criterion): RedirectResponse
    {
        $this->authorize('delete', $criterion);

        if ($criterion->ratings()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('This question has been used in an evaluation — deactivate it instead of deleting.'),
            ]);

            return back();
        }

        $criterion->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Evaluation question deleted.')]);

        return back();
    }
}
