<?php

namespace App\Http\Controllers;

use App\Models\ChecklistItem;
use App\Models\Section;
use App\Models\Store;
use App\Models\Trainee;
use App\Models\User;
use App\Services\Training\TraineeProgress;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, TraineeProgress $progress): Response
    {
        $user = $request->user();
        $storeId = $user->resolveStoreFilter($request->integer('store') ?: null);

        return $user->isSuperAdmin()
            ? $this->superAdminDashboard($storeId, $progress)
            : $this->managerDashboard($user, $progress, $storeId);
    }

    private function superAdminDashboard(?int $storeId, TraineeProgress $progress): Response
    {
        return Inertia::render('dashboard', [
            'isSuperAdmin' => true,
            'filters' => ['store' => $storeId],
            'stats' => [
                'users' => $storeId
                    ? Store::whereKey($storeId)->first()?->managers()->count() ?? 0
                    : User::count(),
                'stores' => $storeId ? 1 : Store::count(),
                'trainees' => Trainee::query()->inStore($storeId)->active()->count(),
                'sections' => Section::published()->count(),
                'items' => ChecklistItem::whereHas('category.section', fn ($query) => $query->published())->count(),
            ],
            'developmentZone' => $this->developmentZone(
                Trainee::query()->inStore($storeId)->active(),
                $progress,
            ),
        ]);
    }

    private function managerDashboard(User $user, TraineeProgress $progress, ?int $storeId): Response
    {
        $trainees = Trainee::visibleTo($user)->inStore($storeId)->active()->with('store:id,name')->orderBy('name')->get();
        $stats = $progress->rosterStats($trainees->pluck('id'));

        // The countable total is global, so read it from the source rather than
        // inferring it from whichever trainee happens to sort first.
        $leafTotal = $progress->leafItemIds()->count();
        $completedSum = array_sum(array_column($stats, 'completed'));
        $ratings = array_filter(array_column($stats, 'average_rating'), fn ($value) => $value !== null);

        return Inertia::render('dashboard', [
            'isSuperAdmin' => false,
            'filters' => ['store' => $storeId],
            'managerStats' => [
                'trainees' => $trainees->count(),
                'completion' => $trainees->count() * $leafTotal > 0
                    ? (int) round(($completedSum / ($trainees->count() * $leafTotal)) * 100)
                    : 0,
                'average_rating' => $ratings !== [] ? round(array_sum($ratings) / count($ratings), 1) : null,
            ],
            'trainees' => $trainees->map(fn (Trainee $trainee): array => [
                'id' => $trainee->id,
                'name' => $trainee->name,
                'position' => $trainee->position,
                'store' => $trainee->store->only(['id', 'name']),
                'stats' => $stats[$trainee->id],
            ])->values(),
            'developmentZone' => $this->developmentZone(
                Trainee::visibleTo($user)->inStore($storeId)->active(),
                $progress,
            ),
        ]);
    }

    /**
     * Trainees currently in the Development Zone, with completion within
     * their own curated plan — the Dashboard's Development Zone panel.
     *
     * @param  Builder<Trainee>  $scope  Already scoped to who/where this viewer may see.
     * @return Collection<int, array{id: int, name: string, position: string|null, store: array{id: int, name: string}, status: string, stats: array{completed: int, total: int}}>
     */
    private function developmentZone(Builder $scope, TraineeProgress $progress): Collection
    {
        $trainees = $scope->inDevelopmentZone()->with('store:id,name')->orderBy('name')->get();
        $stats = $progress->developmentStats($trainees->pluck('id'));

        return $trainees->map(fn (Trainee $trainee): array => [
            'id' => $trainee->id,
            'name' => $trainee->name,
            'position' => $trainee->position,
            'store' => $trainee->store->only(['id', 'name']),
            'status' => $trainee->development_status->value,
            'stats' => $stats[$trainee->id] ?? ['completed' => 0, 'total' => 0],
        ])->values();
    }
}
