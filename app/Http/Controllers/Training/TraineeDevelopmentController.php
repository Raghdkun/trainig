<?php

namespace App\Http\Controllers\Training;

use App\Enums\DevelopmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Training\StoreDevelopmentEvaluationRequest;
use App\Models\ChecklistItem;
use App\Models\DevelopmentEvaluation;
use App\Models\DevelopmentEvaluationCriterion;
use App\Models\Store;
use App\Models\Trainee;
use App\Services\Training\TraineeProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TraineeDevelopmentController extends Controller
{
    /**
     * The Development Zone — every trainee currently in it, with progress
     * against their own curated plan.
     */
    public function index(Request $request, TraineeProgress $progress): Response
    {
        $this->authorize('viewAny', Trainee::class);

        $user = $request->user();
        $canChooseStore = $user->canFilterByStore();
        $storeId = $user->resolveStoreFilter($request->integer('store') ?: null);

        $visible = Trainee::visibleTo($user)->inStore($storeId)->active();

        $trainees = (clone $visible)
            ->inDevelopmentZone()
            ->with('store:id,name')
            ->orderBy('name')
            ->get();

        $stats = $progress->developmentStats($trainees->pluck('id'));

        $addableTrainees = (clone $visible)
            ->whereNull('development_status')
            ->with('store:id,name')
            ->orderBy('name')
            ->get();

        $isSuperAdmin = $user->isSuperAdmin();

        return Inertia::render('training/development-zone/index', [
            'trainees' => $trainees->map(fn (Trainee $trainee): array => [
                'id' => $trainee->id,
                'name' => $trainee->name,
                'position' => $trainee->position,
                'store' => $trainee->store->only(['id', 'name']),
                'status' => $trainee->development_status->value,
                'stats' => $stats[$trainee->id] ?? ['completed' => 0, 'total' => 0],
            ])->values(),
            'addableTrainees' => $addableTrainees->map(fn (Trainee $trainee): array => [
                'id' => $trainee->id,
                'name' => $trainee->name,
                'position' => $trainee->position,
                'store' => $trainee->store->only(['id', 'name']),
            ])->values(),
            'criteria' => DevelopmentEvaluationCriterion::active()->ordered()->get(['id', 'label', 'description']),
            'allCriteria' => $isSuperAdmin
                ? DevelopmentEvaluationCriterion::ordered()->get(['id', 'label', 'description', 'order', 'is_active'])
                : [],
            'canManageCriteria' => $isSuperAdmin,
            'stores' => $canChooseStore
                ? ($isSuperAdmin ? Store::orderBy('name')->get(['id', 'name']) : $user->stores()->orderBy('stores.name')->get(['stores.id', 'stores.name']))
                : [],
            'filters' => ['store' => $storeId],
            'canChooseStore' => $canChooseStore,
        ]);
    }

    /**
     * A trainee's Development Zone detail — their submitted evaluation and
     * their curated development plan.
     */
    public function show(Trainee $trainee, TraineeProgress $progress): Response
    {
        $this->authorize('view', $trainee);

        $trainee->load('store', 'latestDevelopmentEvaluation.evaluator', 'latestDevelopmentEvaluation.ratings.criterion');
        $canManagePlan = request()->user()->isSuperAdmin();
        $evaluation = $trainee->latestDevelopmentEvaluation;

        return Inertia::render('training/development-zone/show', [
            'trainee' => [
                'id' => $trainee->id,
                'name' => $trainee->name,
                'position' => $trainee->position,
                'store' => $trainee->store->only(['id', 'name']),
                'status' => $trainee->development_status?->value,
                'archived_at' => $trainee->archived_at?->toIso8601String(),
            ],
            'evaluation' => $evaluation ? [
                'id' => $evaluation->id,
                'evaluator' => $evaluation->evaluator?->only(['id', 'name']),
                'notes' => $evaluation->notes,
                'submitted_at' => $evaluation->submitted_at->toIso8601String(),
                'ratings' => $evaluation->ratings->map(fn ($rating): array => [
                    'criterion' => $rating->criterion->only(['id', 'label', 'description']),
                    'rating' => $rating->rating,
                ])->values(),
            ] : null,
            'developmentPlan' => $progress->developmentPlan($trainee),
            'developmentPicker' => $canManagePlan ? $progress->pickerTree() : [],
            'canManagePlan' => $canManagePlan,
            'canComplete' => $canManagePlan,
            'canRemove' => $canManagePlan,
        ]);
    }

    /**
     * Add a trainee to the Development Zone by submitting the Manager's
     * rubric evaluation of their current performance. They land as Pending
     * until an admin reviews it and builds their plan.
     */
    public function store(StoreDevelopmentEvaluationRequest $request, Trainee $trainee): RedirectResponse
    {
        $this->authorize('addToDevelopmentZone', $trainee);

        if ($trainee->development_status !== null) {
            throw ValidationException::withMessages(['trainee' => __('This trainee is already in the Development Zone.')]);
        }

        $data = $request->evaluationData();

        DB::transaction(function () use ($trainee, $request, $data): void {
            $evaluation = DevelopmentEvaluation::create([
                'trainee_id' => $trainee->id,
                'evaluated_by' => $request->user()->id,
                'notes' => $data['notes'],
                'submitted_at' => now(),
            ]);

            $evaluation->ratings()->createMany(array_map(
                fn (array $rating): array => [
                    'development_evaluation_criterion_id' => $rating['criterion_id'],
                    'rating' => $rating['rating'],
                ],
                $data['ratings'],
            ));

            $trainee->update(['development_status' => DevelopmentStatus::Pending]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Trainee added to the Development Zone.')]);

        return back();
    }

    /**
     * Remove a trainee from the Development Zone. Their evaluation and plan
     * history is left in place (just hidden) in case they're added again.
     */
    public function destroy(Trainee $trainee): RedirectResponse
    {
        $this->authorize('removeFromDevelopmentZone', $trainee);

        $trainee->update(['development_status' => null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Trainee removed from the Development Zone.')]);

        return back();
    }

    /**
     * Replace which existing checklist items make up this trainee's
     * Development Plan. Only real leaf items from published stations are
     * ever accepted — anything else submitted is silently dropped rather
     * than erroring, since the picker itself never offers them. Finalizing
     * a plan for a Pending trainee moves them to Active.
     */
    public function updatePlan(Request $request, Trainee $trainee): RedirectResponse
    {
        $this->authorize('manageDevelopmentPlan', $trainee);

        $requested = collect($request->input('item_ids', []))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id);

        $validIds = ChecklistItem::query()
            ->whereDoesntHave('children')
            ->whereHas('category.section', fn ($query) => $query->published())
            ->whereIn('id', $requested)
            ->pluck('id');

        $trainee->developmentItems()->sync($validIds);

        if ($trainee->development_status === DevelopmentStatus::Pending) {
            $trainee->update(['development_status' => DevelopmentStatus::Active]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Development plan updated.')]);

        return back();
    }

    /**
     * Mark an Active trainee's development complete.
     */
    public function complete(Trainee $trainee): RedirectResponse
    {
        $this->authorize('completeDevelopment', $trainee);

        if ($trainee->development_status !== DevelopmentStatus::Active) {
            throw ValidationException::withMessages(['trainee' => __('Only an active development plan can be marked complete.')]);
        }

        $trainee->update(['development_status' => DevelopmentStatus::Completed]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Development marked complete.')]);

        return back();
    }
}
