<?php

namespace App\Http\Controllers\Training;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Training\TraineeRequest;
use App\Models\Store;
use App\Models\Trainee;
use App\Models\User;
use App\Services\Training\TraineeProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TraineeController extends Controller
{
    /**
     * Roster report — scoped to the manager's assigned trainees (super admins
     * see all and may filter by store).
     */
    public function index(Request $request, TraineeProgress $progress): Response
    {
        $this->authorize('viewAny', Trainee::class);

        $user = $request->user();
        $canChooseStore = $user->canFilterByStore();
        $storeId = $user->resolveStoreFilter($request->integer('store') ?: null);
        $tab = $request->query('tab') === 'archived' ? 'archived' : 'active';

        $visible = Trainee::visibleTo($user)->inStore($storeId);

        $trainees = (clone $visible)
            ->when($tab === 'archived', fn ($query) => $query->archived(), fn ($query) => $query->active())
            ->with('store')
            ->orderBy('name')
            ->get();

        $stats = $progress->rosterStats($trainees->pluck('id'));

        return Inertia::render('training/trainees/index', [
            'trainees' => $trainees->map(fn (Trainee $trainee): array => [
                'id' => $trainee->id,
                'name' => $trainee->name,
                'position' => $trainee->position,
                'store' => $trainee->store->only(['id', 'name']),
                'stats' => $stats[$trainee->id],
            ])->all(),
            'stores' => $canChooseStore
                ? ($user->isSuperAdmin() ? Store::orderBy('name')->get(['id', 'name']) : $user->stores()->orderBy('stores.name')->get(['stores.id', 'stores.name']))
                : [],
            'filters' => ['store' => $storeId, 'tab' => $tab],
            'canChooseStore' => $canChooseStore,
            'traineeCounts' => [
                'active' => (clone $visible)->active()->count(),
                'archived' => (clone $visible)->archived()->count(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Trainee::class);

        $stores = $this->assignableStores($request->user());

        return Inertia::render('training/trainees/create', [
            'stores' => $stores,
            'canChooseStore' => $request->user()->isSuperAdmin() || $stores->count() > 1,
        ]);
    }

    public function store(TraineeRequest $request): RedirectResponse
    {
        $this->authorize('create', Trainee::class);

        $user = $request->user();
        $isManager = $user->isManager();
        $storeId = $request->integer('store_id') ?: null;

        // A single-store manager doesn't need to choose — default to their store.
        if (! $storeId && $isManager && $user->stores->count() === 1) {
            $storeId = (int) $user->stores->first()->id;
        }

        $allowedStoreIds = $user->isSuperAdmin()
            ? Store::pluck('id')
            : $user->stores->pluck('id');

        if (! $storeId || ! $allowedStoreIds->contains($storeId)) {
            throw ValidationException::withMessages(['store_id' => __('Please choose a store.')]);
        }

        $trainee = DB::transaction(function () use ($request, $user, $storeId, $isManager): Trainee {
            $trainee = Trainee::create([
                'name' => $request->validated('name'),
                'position' => $request->validated('position'),
                'hired_at' => $request->validated('hired_at'),
                'store_id' => $storeId,
                'created_by' => $user->id,
            ]);

            // Managers automatically own the trainees they create.
            if ($isManager) {
                $trainee->managers()->attach($user->id);
            }

            return $trainee;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Trainee added.')]);

        return to_route('trainees.show', $trainee);
    }

    public function show(Request $request, Trainee $trainee, TraineeProgress $progress): Response
    {
        $this->authorize('view', $trainee);

        $trainee->load('store', 'managers:id,name', 'archivedBy:id,name');
        $isSuperAdmin = $request->user()->isSuperAdmin();

        return Inertia::render('training/trainees/show', [
            'trainee' => [
                'id' => $trainee->id,
                'name' => $trainee->name,
                'position' => $trainee->position,
                'hired_at' => $trainee->hired_at?->toDateString(),
                'store' => $trainee->store->only(['id', 'name']),
                'managers' => $trainee->managers->map->only(['id', 'name'])->values(),
                'archived_at' => $trainee->archived_at?->toIso8601String(),
                'archived_by' => $trainee->archivedBy?->only(['id', 'name']),
            ],
            'progress' => $progress->detail($trainee),
            'canManage' => $request->user()->can('update', $trainee),
            'canDelete' => $request->user()->can('delete', $trainee),
            'canAssignManagers' => $isSuperAdmin,
            'availableManagers' => $isSuperAdmin
                ? User::where('role', Role::Manager)
                    ->whereHas('stores', fn ($query) => $query->whereKey($trainee->store_id))
                    ->orderBy('name')
                    ->get(['id', 'name'])
                : [],
        ]);
    }

    public function edit(Request $request, Trainee $trainee): Response
    {
        $this->authorize('update', $trainee);

        $stores = $this->assignableStores($request->user());

        return Inertia::render('training/trainees/edit', [
            'trainee' => $trainee->only(['id', 'name', 'position', 'hired_at', 'store_id']),
            'stores' => $stores,
            'canChooseStore' => $request->user()->isSuperAdmin() || $stores->count() > 1,
        ]);
    }

    public function update(TraineeRequest $request, Trainee $trainee): RedirectResponse
    {
        $this->authorize('update', $trainee);

        $attributes = [
            'name' => $request->validated('name'),
            'position' => $request->validated('position'),
            'hired_at' => $request->validated('hired_at'),
        ];

        $user = $request->user();
        $storeId = $request->integer('store_id');

        if ($storeId) {
            $allowedStoreIds = $user->isSuperAdmin() ? Store::pluck('id') : $user->stores->pluck('id');

            // Don't silently drop a store the user may not use and then report
            // success — tell them the change was refused.
            if (! $allowedStoreIds->contains($storeId)) {
                throw ValidationException::withMessages([
                    'store_id' => __('You cannot move a trainee to that store.'),
                ]);
            }

            $attributes['store_id'] = $storeId;
        }

        $trainee->update($attributes);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Trainee updated.')]);

        return to_route('trainees.show', $trainee);
    }

    public function destroy(Trainee $trainee): RedirectResponse
    {
        $this->authorize('delete', $trainee);

        $trainee->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Trainee removed.')]);

        return to_route('trainees.index');
    }

    /**
     * Retire a trainee from the active roster. Nothing is deleted — their
     * full evaluation history stays intact and reachable under the Archived
     * tab (and in Reports, when "include archived" is checked).
     */
    public function archive(Request $request, Trainee $trainee): RedirectResponse
    {
        $this->authorize('update', $trainee);

        $trainee->update([
            'archived_at' => now(),
            'archived_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Trainee archived.')]);

        return back();
    }

    /**
     * Bring an archived trainee back onto the active roster — e.g. archived
     * by mistake, or they return for refresher training.
     */
    public function restore(Trainee $trainee): RedirectResponse
    {
        $this->authorize('update', $trainee);

        $trainee->update(['archived_at' => null, 'archived_by' => null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Trainee restored.')]);

        return back();
    }

    /**
     * Stores a trainee may be assigned to: all stores for super admins, the
     * manager's own stores otherwise.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    private function assignableStores(User $user): Collection
    {
        $query = $user->isSuperAdmin() ? Store::query() : $user->stores();

        return $query->orderBy('name')
            ->get(['stores.id', 'stores.name'])
            ->map(fn (Store $store): array => ['id' => $store->id, 'name' => $store->name])
            ->values();
    }
}
