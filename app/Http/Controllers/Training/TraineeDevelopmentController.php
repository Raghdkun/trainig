<?php

namespace App\Http\Controllers\Training;

use App\Actions\Training\SubmitDevelopmentEvaluation;
use App\Enums\DevelopmentStatus;
use App\Enums\EvaluationGrade;
use App\Enums\Position;
use App\Http\Controllers\Controller;
use App\Http\Requests\Training\StoreDevelopmentEmployeeRequest;
use App\Http\Requests\Training\StoreDevelopmentEvaluationRequest;
use App\Http\Requests\Training\StoreDevelopmentReassessmentRequest;
use App\Models\ChecklistItem;
use App\Models\DevelopmentEvaluation;
use App\Models\DevelopmentSkillScore;
use App\Models\Store;
use App\Models\Trainee;
use App\Models\User;
use App\Services\Training\StationAssessment;
use App\Services\Training\TraineeProgress;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        $isSuperAdmin = $user->isSuperAdmin();

        return Inertia::render('training/development-zone/index', [
            'trainees' => $trainees->map(fn (Trainee $trainee): array => [
                'id' => $trainee->id,
                'name' => $trainee->name,
                'position' => $trainee->position,
                'store' => $trainee->store->only(['id', 'name']),
                'status' => $trainee->development_status->value,
                'development_only' => $trainee->development_only,
                'stats' => $stats[$trainee->id] ?? ['completed' => 0, 'total' => 0],
            ])->values(),
            'canSetupAssessment' => $isSuperAdmin,
            'canAddToZone' => $this->addableTrainees($user)->exists() || $this->canAddEmployee($user),
            'stores' => $canChooseStore
                ? ($isSuperAdmin ? Store::orderBy('name')->get(['id', 'name']) : $user->stores()->orderBy('stores.name')->get(['stores.id', 'stores.name']))
                : [],
            'filters' => ['store' => $storeId],
            'canChooseStore' => $canChooseStore,
        ]);
    }

    /**
     * The full-page evaluation form for bringing someone into the Development
     * Zone — an existing trainee, or a brand-new employee entered here — with
     * the stations & skills assessment questions to answer.
     */
    public function create(Request $request, StationAssessment $assessment): Response
    {
        $this->authorize('viewAny', Trainee::class);

        $user = $request->user();
        $addableTrainees = $this->addableTrainees($user)->with('store:id,name')->orderBy('name')->get();
        $requestedTraineeId = $request->integer('trainee') ?: null;

        return Inertia::render('training/development-zone/create', [
            'addableTrainees' => $addableTrainees->map(fn (Trainee $trainee): array => [
                'id' => $trainee->id,
                'name' => $trainee->name,
                'position' => $trainee->position,
                'store' => $trainee->store->only(['id', 'name']),
            ])->values(),
            'selectedTraineeId' => $addableTrainees->contains('id', $requestedTraineeId) ? $requestedTraineeId : null,
            'skills' => $assessment->form(),
            'canAddEmployee' => $this->canAddEmployee($user),
            'employeeStores' => $this->employeeStores($user),
            'positionOptions' => Position::options(),
            'gradeOptions' => array_map(fn (EvaluationGrade $grade): string => $grade->value, EvaluationGrade::cases()),
        ]);
    }

    /**
     * A trainee's Development Zone detail — the manager's evaluation, the
     * latest station & skill ratings (with Development Needs and before →
     * after once reassessed), and their curated development plan.
     */
    public function show(Request $request, Trainee $trainee, TraineeProgress $progress, StationAssessment $assessment): Response
    {
        $this->authorize('view', $trainee);

        $trainee->load('store');
        $canManagePlan = $request->user()->isSuperAdmin();

        // Newest first. The baseline is the manager's evaluation that brought
        // them into the zone; reassessments after it measure improvement.
        $evaluations = $trainee->developmentEvaluations()
            ->with(['evaluator:id,name', 'skillScores.skill:id,name,section_id', 'ratings.criterion'])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get();
        $baselineIndex = $evaluations->search(fn (DevelopmentEvaluation $evaluation): bool => ! $evaluation->is_reassessment);
        $evaluation = $baselineIndex === false ? null : $evaluations[$baselineIndex];
        $history = $baselineIndex === false ? collect() : $evaluations->slice(0, $baselineIndex + 1)->reverse()->values();
        $latest = $history->last();
        $skillRatings = $latest ? $this->skillRatings($latest, $evaluation, $assessment) : [];

        return Inertia::render('training/development-zone/show', [
            'trainee' => [
                'id' => $trainee->id,
                'name' => $trainee->name,
                'position' => $trainee->position,
                'store' => $trainee->store->only(['id', 'name']),
                'status' => $trainee->development_status?->value,
                'development_only' => $trainee->development_only,
                'hired_at' => $trainee->hired_at?->toDateString(),
                'archived_at' => $trainee->archived_at?->toIso8601String(),
            ],
            'evaluation' => $evaluation ? [
                'id' => $evaluation->id,
                'evaluator' => $evaluation->evaluator?->only(['id', 'name']),
                'grade' => $evaluation->grade?->value,
                'points' => $evaluation->points,
                'notes' => $evaluation->notes,
                'submitted_at' => $evaluation->submitted_at->toIso8601String(),
                // Criteria ratings from before the station assessment replaced them.
                'ratings' => $evaluation->ratings->map(fn ($rating): array => [
                    'criterion' => $rating->criterion->only(['id', 'label', 'description']),
                    'rating' => $rating->rating,
                ])->values(),
            ] : null,
            'skillRatings' => $skillRatings,
            'assessmentHistory' => $history->map(fn (DevelopmentEvaluation $entry): array => [
                'id' => $entry->id,
                'is_reassessment' => $entry->is_reassessment,
                'evaluator' => $entry->evaluator?->only(['id', 'name']),
                'notes' => $entry->is_reassessment ? $entry->notes : null,
                'submitted_at' => $entry->submitted_at->toIso8601String(),
                'average_stars' => $entry->skillScores->isEmpty()
                    ? null
                    : round((float) $entry->skillScores->avg('stars') * 4) / 4,
            ])->values(),
            'developmentPlan' => $progress->developmentPlan($trainee),
            'developmentPicker' => $canManagePlan ? $progress->pickerTree() : [],
            'canManagePlan' => $canManagePlan,
            'canComplete' => $canManagePlan,
            'canRemove' => $canManagePlan,
            'canReassess' => $this->canReassess($request->user(), $trainee) && $assessment->skills()->isNotEmpty(),
        ]);
    }

    /**
     * The training team's reassessment form — the same questions, with each
     * station/skill's previous rating alongside for reference.
     */
    public function reassess(Request $request, Trainee $trainee, StationAssessment $assessment): Response|RedirectResponse
    {
        $this->authorize('reassessDevelopment', $trainee);

        if (! $this->canReassess($request->user(), $trainee)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Reassess once the development plan is active.')]);

            return to_route('development-zone.show', $trainee);
        }

        $trainee->load('store:id,name');
        $previous = $trainee->developmentEvaluations()
            ->with('skillScores')
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->first();

        return Inertia::render('training/development-zone/reassess', [
            'trainee' => [
                'id' => $trainee->id,
                'name' => $trainee->name,
                'position' => $trainee->position,
                'store' => $trainee->store->only(['id', 'name']),
            ],
            'skills' => $assessment->form(),
            'previousStars' => $previous
                ? $previous->skillScores->whereNotNull('assessment_skill_id')->mapWithKeys(
                    fn (DevelopmentSkillScore $score): array => [$score->assessment_skill_id => $score->stars],
                )
                : (object) [],
            'previousAssessedAt' => $previous?->submitted_at->toIso8601String(),
        ]);
    }

    /**
     * Record a reassessment. The workflow status doesn't change — it only
     * adds new station ratings to compare against the original evaluation.
     */
    public function storeReassessment(StoreDevelopmentReassessmentRequest $request, Trainee $trainee, SubmitDevelopmentEvaluation $submit): RedirectResponse
    {
        $this->authorize('reassessDevelopment', $trainee);

        if (! $this->canReassess($request->user(), $trainee)) {
            throw ValidationException::withMessages(['answers' => __('Reassess once the development plan is active.')]);
        }

        $submit->handle($trainee, $request->user(), $request->reassessmentData(), isReassessment: true);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reassessment saved.')]);

        return to_route('development-zone.show', $trainee);
    }

    /**
     * Add a trainee to the Development Zone by submitting the Manager's
     * rubric evaluation of their current performance. They land as Pending
     * until an admin reviews it and builds their plan.
     */
    public function store(StoreDevelopmentEvaluationRequest $request, Trainee $trainee, SubmitDevelopmentEvaluation $submit): RedirectResponse
    {
        $this->authorize('addToDevelopmentZone', $trainee);

        if ($trainee->development_status !== null) {
            throw ValidationException::withMessages(['trainee' => __('This trainee is already in the Development Zone.')]);
        }

        $submit->handle($trainee, $request->user(), $request->evaluationData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Trainee added to the Development Zone.')]);

        return to_route('development-zone.show', $trainee);
    }

    /**
     * Add a brand-new employee — someone not on the Trainees roster — straight
     * into the Development Zone with the manager's evaluation. They follow
     * the same Pending → plan → Active workflow, but stay off the roster.
     */
    public function storeEmployee(StoreDevelopmentEmployeeRequest $request, SubmitDevelopmentEvaluation $submit): RedirectResponse
    {
        $this->authorize('addDevelopmentEmployee', Trainee::class);

        $user = $request->user();
        $storeId = $request->integer('store_id') ?: null;

        // A single-store manager doesn't need to choose — default to their store.
        if (! $storeId && $user->isManager() && $user->stores->count() === 1) {
            $storeId = (int) $user->stores->first()->id;
        }

        $allowedStoreIds = $user->isSuperAdmin()
            ? Store::pluck('id')
            : $user->stores->pluck('id');

        if (! $storeId || ! $allowedStoreIds->contains($storeId)) {
            throw ValidationException::withMessages(['store_id' => __('Please choose a store.')]);
        }

        $employee = DB::transaction(function () use ($request, $user, $storeId, $submit): Trainee {
            $employee = Trainee::create([
                'name' => $request->validated('name'),
                'position' => $request->validated('position'),
                'hired_at' => $request->validated('hired_at'),
                'store_id' => $storeId,
                'created_by' => $user->id,
                'development_only' => true,
            ]);

            if ($user->isManager()) {
                $employee->managers()->attach($user->id);
            }

            $submit->handle($employee, $user, $request->evaluationData());

            return $employee;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee added to the Development Zone.')]);

        return to_route('development-zone.show', $employee);
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
     * a plan with at least one item moves a Pending trainee to Active — an
     * empty plan leaves them Pending, since there's nothing to work on yet.
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

        if ($trainee->development_status === DevelopmentStatus::Pending && $validIds->isNotEmpty()) {
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

    /**
     * Reopen a Completed trainee's development, moving them back to Active
     * so their plan can carry on (e.g. it was marked complete too early).
     */
    public function reopen(Trainee $trainee): RedirectResponse
    {
        $this->authorize('completeDevelopment', $trainee);

        if ($trainee->development_status !== DevelopmentStatus::Completed) {
            throw ValidationException::withMessages(['trainee' => __('Only a completed development plan can be reopened.')]);
        }

        $trainee->update(['development_status' => DevelopmentStatus::Active]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Development reopened — back to Active.')]);

        return back();
    }

    /**
     * Roster trainees this user may bring into the Development Zone — active,
     * visible to them, and not already in it.
     *
     * @return Builder<Trainee>
     */
    private function addableTrainees(User $user): Builder
    {
        return Trainee::visibleTo($user)->active()->onRoster()->whereNull('development_status');
    }

    private function canAddEmployee(User $user): bool
    {
        return $user->can('addDevelopmentEmployee', Trainee::class) && $this->employeeStores($user)->isNotEmpty();
    }

    /**
     * Stores a new Development Zone employee may be placed in.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    private function employeeStores(User $user): Collection
    {
        $stores = $user->isSuperAdmin()
            ? Store::orderBy('name')->get(['id', 'name'])
            : $user->stores()->orderBy('stores.name')->get(['stores.id', 'stores.name']);

        return $stores->map(fn (Store $store): array => ['id' => $store->id, 'name' => $store->name])->values()->toBase();
    }

    /**
     * Reassessing is for the training team, once a plan is in place (Active)
     * or finished (Completed) — never for an archived employee.
     */
    private function canReassess(User $user, Trainee $trainee): bool
    {
        return $user->can('reassessDevelopment', $trainee)
            && ! $trainee->isArchived()
            && in_array($trainee->development_status, [DevelopmentStatus::Active, DevelopmentStatus::Completed], true);
    }

    /**
     * The latest assessment's station & skill ratings, each with the original
     * evaluation's rating for comparison, whether it's a Development Need,
     * and the content station it links to (for plan suggestions).
     *
     * @return list<array{skill_id: int|null, name: string, section_id: int|null, stars: float, baseline_stars: float|null, is_need: bool}>
     */
    private function skillRatings(DevelopmentEvaluation $latest, ?DevelopmentEvaluation $baseline, StationAssessment $assessment): array
    {
        $scores = $latest->skillScores->values();
        $needs = $assessment->developmentNeeds($scores->map(fn (DevelopmentSkillScore $score): array => [
            'stars' => $score->stars,
        ]));
        $baselineStars = $baseline && ! $baseline->is($latest)
            ? $baseline->skillScores->whereNotNull('assessment_skill_id')->pluck('stars', 'assessment_skill_id')
            : collect();

        return $scores->map(fn (DevelopmentSkillScore $score, int $index): array => [
            'skill_id' => $score->assessment_skill_id,
            'name' => $score->skill?->name ?? $score->skill_name,
            // The skill's current link, so relinking it updates suggestions.
            'section_id' => $score->skill ? $score->skill->section_id : $score->section_id,
            'stars' => $score->stars,
            'baseline_stars' => $score->assessment_skill_id !== null ? $baselineStars->get($score->assessment_skill_id) : null,
            'is_need' => in_array($index, $needs, true),
        ])->values()->all();
    }
}
