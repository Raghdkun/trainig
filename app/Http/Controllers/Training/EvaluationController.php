<?php

namespace App\Http\Controllers\Training;

use App\Actions\Training\RecordEvaluation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Training\EvaluationRequest;
use App\Models\ChecklistItem;
use App\Models\Trainee;
use Illuminate\Http\RedirectResponse;

class EvaluationController extends Controller
{
    /**
     * Record (or update) the evaluation of one checklist item for a trainee,
     * cascading completion to sub-items.
     */
    public function update(
        EvaluationRequest $request,
        Trainee $trainee,
        ChecklistItem $checklistItem,
        RecordEvaluation $recordEvaluation,
    ): RedirectResponse {
        $this->authorize('evaluate', $trainee);

        abort_if($trainee->isArchived(), 403, 'This trainee is archived and read-only. Restore them to make changes.');

        $recordEvaluation->handle(
            $trainee,
            $checklistItem,
            $request->evaluationData(),
            $request->user(),
        );

        return back();
    }
}
