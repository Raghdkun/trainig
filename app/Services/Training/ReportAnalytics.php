<?php

namespace App\Services\Training;

use App\Enums\Importance;
use App\Enums\Role;
use App\Models\ChecklistItem;
use App\Models\Evaluation;
use App\Models\Section;
use App\Models\Store;
use App\Models\Trainee;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aggregation engine behind the Reports hub. Every method takes a resolved
 * {@see ReportScope} so completion and score roll-ups stay constrained to the
 * trainees the current user may see. Completion always counts leaf items only
 * (via {@see TraineeProgress::leafItemIds()}); time and score bucketing happen
 * in PHP so the queries remain portable between PostgreSQL and SQLite.
 */
class ReportAnalytics
{
    /** Below this average score an in-progress trainee is flagged at-risk. */
    private const AT_RISK_SCORE = 50;

    /** Days without a completion before a trainee is considered stalled. */
    private const STALE_DAYS = 14;

    /** Trend window options (weeks); the first is the default. */
    public const WEEK_OPTIONS = [12, 4, 26];

    public function __construct(private readonly TraineeProgress $progress) {}

    /**
     * Resolve the reporting scope for a user + request filters.
     *
     * @param  array{store?: int|null, weeks?: int|null}  $filters
     */
    public function for(User $user, array $filters = []): ReportScope
    {
        $storeId = $user->isSuperAdmin() ? ($filters['store'] ?? null) : null;

        $weeks = (int) ($filters['weeks'] ?? self::WEEK_OPTIONS[0]);
        if (! in_array($weeks, self::WEEK_OPTIONS, true)) {
            $weeks = self::WEEK_OPTIONS[0];
        }

        $traineeIds = Trainee::query()
            ->visibleTo($user)
            ->inStore($storeId)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return new ReportScope($user, $traineeIds, $storeId, $weeks);
    }

    /**
     * Headline KPIs for the overview panel.
     *
     * @return array{trainees: int, completion: int, average_score: float|null, fully_trained: int, at_risk: int, in_progress: int, not_started: int, evaluations_recorded: int}
     */
    public function overview(ReportScope $scope): array
    {
        $rows = $this->traineeRows($scope);
        $counts = ['completed' => 0, 'at_risk' => 0, 'in_progress' => 0, 'not_started' => 0];

        foreach ($rows as $row) {
            $counts[$row['status']]++;
        }

        $leafTotal = $this->progress->leafItemIds()->count();
        $trainees = count($scope->traineeIds);
        $completedSum = array_sum(array_column($rows, 'completed_count'));
        $denominator = $trainees * $leafTotal;

        return [
            'trainees' => $trainees,
            'completion' => $denominator > 0 ? (int) round($completedSum / $denominator * 100) : 0,
            'average_score' => $this->averageScore($scope),
            'fully_trained' => $counts['completed'],
            'at_risk' => $counts['at_risk'],
            'in_progress' => $counts['in_progress'],
            'not_started' => $counts['not_started'],
            'evaluations_recorded' => $scope->isEmpty() ? 0 : Evaluation::query()
                ->whereIn('trainee_id', $scope->traineeIds)
                ->count(),
        ];
    }

    /**
     * Completed leaf evaluations bucketed by ISO week over the scope window.
     *
     * @return array<int, array{week: string, label: string, count: int}>
     */
    public function completionTrend(ReportScope $scope): array
    {
        $start = Carbon::now()->startOfWeek()->subWeeks($scope->weeks - 1);

        /** @var array<string, array{week: string, label: string, count: int}> $buckets */
        $buckets = [];
        for ($i = 0; $i < $scope->weeks; $i++) {
            $weekStart = $start->copy()->addWeeks($i);
            $buckets[$weekStart->toDateString()] = [
                'week' => $weekStart->toDateString(),
                'label' => $weekStart->format('M j'),
                'count' => 0,
            ];
        }

        if (! $scope->isEmpty()) {
            $timestamps = Evaluation::query()
                ->whereIn('trainee_id', $scope->traineeIds)
                ->whereIn('checklist_item_id', $this->progress->leafItemIds())
                ->where('completed', true)
                ->whereNotNull('completed_at')
                ->where('completed_at', '>=', $start)
                ->pluck('completed_at');

            foreach ($timestamps as $timestamp) {
                $key = $timestamp->copy()->startOfWeek()->toDateString();
                if (isset($buckets[$key])) {
                    $buckets[$key]['count']++;
                }
            }
        }

        return array_values($buckets);
    }

    /**
     * Rating counts across 20-point bands.
     *
     * @return array<int, array{band: string, count: int}>
     */
    public function scoreDistribution(ReportScope $scope): array
    {
        $labels = ['0–20', '21–40', '41–60', '61–80', '81–100'];
        $counts = array_fill(0, 5, 0);

        if (! $scope->isEmpty()) {
            $ratings = Evaluation::query()
                ->whereIn('trainee_id', $scope->traineeIds)
                ->whereNotNull('rating')
                ->pluck('rating');

            foreach ($ratings as $rating) {
                $counts[min((int) ($rating / 20.0001), 4)]++;
            }
        }

        return array_map(
            fn (int $index): array => ['band' => $labels[$index], 'count' => $counts[$index]],
            array_keys($labels),
        );
    }

    /**
     * Completion % and average score per store, ranked by completion.
     *
     * @return array<int, array{id: int, name: string, trainees: int, completion: int, average_score: float|null}>
     */
    public function storePerformance(ReportScope $scope): array
    {
        if ($scope->isEmpty()) {
            return [];
        }

        $trainees = Trainee::query()
            ->whereIn('id', $scope->traineeIds)
            ->get(['id', 'store_id']);

        $leafTotal = $this->progress->leafItemIds()->count();
        $stats = $this->progress->rosterStats($scope->traineeIds);
        $storeNames = Store::query()
            ->whereIn('id', $trainees->pluck('store_id')->unique())
            ->pluck('name', 'id');

        $avgByStore = Evaluation::query()
            ->join('trainees', 'trainees.id', '=', 'evaluations.trainee_id')
            ->whereIn('evaluations.trainee_id', $scope->traineeIds)
            ->whereNotNull('rating')
            ->groupBy('trainees.store_id')
            ->selectRaw('trainees.store_id as store_id, avg(rating) as average')
            ->pluck('average', 'store_id');

        $rows = $trainees
            ->groupBy('store_id')
            ->map(function ($group, $storeId) use ($leafTotal, $stats, $avgByStore, $storeNames): array {
                $count = $group->count();
                $completed = $group->sum(fn (Trainee $t): int => $stats[$t->id]['completed'] ?? 0);
                $denominator = $count * $leafTotal;

                return [
                    'id' => (int) $storeId,
                    'name' => (string) ($storeNames[$storeId] ?? '—'),
                    'trainees' => $count,
                    'completion' => $denominator > 0 ? (int) round($completed / $denominator * 100) : 0,
                    'average_score' => isset($avgByStore[$storeId])
                        ? round((float) $avgByStore[$storeId], 1)
                        : null,
                ];
            })
            ->values()
            ->all();

        usort($rows, fn (array $a, array $b): int => $b['completion'] <=> $a['completion'] ?: strcmp($a['name'], $b['name']));

        return $rows;
    }

    /**
     * Per-manager activity. Super admins see every manager (optionally store
     * filtered); a manager sees only themselves.
     *
     * @return array<int, array{id: int, name: string, store: string|null, assigned_trainees: int, evaluations_recorded: int, average_score: float|null}>
     */
    public function managerActivity(ReportScope $scope): array
    {
        $managers = User::query()
            ->where('role', Role::Manager)
            ->when(! $scope->isSuperAdmin(), fn ($query) => $query->whereKey($scope->user->id))
            ->when(
                $scope->isSuperAdmin() && $scope->storeId,
                fn ($query) => $query->whereHas('stores', fn ($inner) => $inner->whereKey($scope->storeId)),
            )
            ->with('stores:id,name')
            ->orderBy('name')
            ->get();

        $assigned = $scope->isEmpty() ? collect() : DB::table('manager_trainee')
            ->whereIn('trainee_id', $scope->traineeIds)
            ->groupBy('user_id')
            ->selectRaw('user_id, count(*) as total')
            ->pluck('total', 'user_id');

        $recorded = $scope->isEmpty() ? [] : DB::table('evaluations')
            ->whereIn('trainee_id', $scope->traineeIds)
            ->whereNotNull('evaluated_by')
            ->groupBy('evaluated_by')
            ->selectRaw('evaluated_by, count(*) as total, avg(rating) as average')
            ->get()
            ->keyBy('evaluated_by')
            ->all();

        return $managers->map(function (User $manager) use ($assigned, $recorded): array {
            $stats = $recorded[$manager->id] ?? null;

            return [
                'id' => $manager->id,
                'name' => $manager->name,
                'store' => $manager->stores->pluck('name')->implode(', ') ?: null,
                'assigned_trainees' => (int) ($assigned[$manager->id] ?? 0),
                'evaluations_recorded' => $stats !== null ? (int) $stats->total : 0,
                'average_score' => $stats !== null && $stats->average !== null
                    ? round((float) $stats->average, 1)
                    : null,
            ];
        })->all();
    }

    /**
     * Per-trainee status roster plus the average onboarding time.
     *
     * @return array{rows: array<int, array{id: int, name: string, position: string|null, store: string, status: string, completion: int, average_score: float|null, last_activity: string|null}>, onboarding_days_avg: float|null}
     */
    public function traineeStatus(ReportScope $scope): array
    {
        $rows = $this->traineeRows($scope);

        $durations = [];
        foreach ($rows as $row) {
            if ($row['status'] === 'completed' && $row['onboarding_days'] !== null) {
                $durations[] = $row['onboarding_days'];
            }
        }

        $public = array_map(fn (array $row): array => [
            'id' => $row['id'],
            'name' => $row['name'],
            'position' => $row['position'],
            'store' => $row['store'],
            'status' => $row['status'],
            'completion' => $row['completion'],
            'average_score' => $row['average_score'],
            'last_activity' => $row['last_activity'],
        ], $rows);

        return [
            'rows' => $public,
            'onboarding_days_avg' => $durations === []
                ? null
                : round(array_sum($durations) / count($durations), 1),
        ];
    }

    /**
     * Section/category roll-ups plus the lowest-scoring leaf items.
     *
     * @return array{sections: array<int, array{id: int, title: string, average_score: float|null, completion: int}>, categories: array<int, array{id: int, title: string, section_title: string, average_score: float|null, completion: int}>, problem_items: array<int, array{id: int, title: string, category_title: string, section_title: string, average_score: float, evaluations: int}>}
     */
    public function stationInsights(ReportScope $scope): array
    {
        $traineeCount = count($scope->traineeIds);
        $leafIds = $this->progress->leafItemIds();

        $leafByCategory = ChecklistItem::query()
            ->whereDoesntHave('children')
            ->selectRaw('category_id, count(*) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $avgByCategory = $this->groupedAverage($scope, 'checklist_items.category_id');
        $avgBySection = $this->groupedAverage($scope, 'categories.section_id', joinCategories: true);
        $doneByCategory = $this->groupedCompleted($scope, 'checklist_items.category_id', $leafIds);
        $doneBySection = $this->groupedCompleted($scope, 'categories.section_id', $leafIds, joinCategories: true);

        $sections = Section::query()->ordered()->with('categories:id,section_id,title,order')->get();

        $sectionRows = [];
        $categoryRows = [];

        foreach ($sections as $section) {
            $sectionLeaves = 0;

            foreach ($section->categories as $category) {
                $leaves = (int) ($leafByCategory[$category->id] ?? 0);
                $sectionLeaves += $leaves;
                $denominator = $leaves * $traineeCount;

                $categoryRows[] = [
                    'id' => $category->id,
                    'title' => $category->title,
                    'section_title' => $section->title,
                    'average_score' => isset($avgByCategory[$category->id])
                        ? round((float) $avgByCategory[$category->id], 1)
                        : null,
                    'completion' => $denominator > 0
                        ? (int) round((int) ($doneByCategory[$category->id] ?? 0) / $denominator * 100)
                        : 0,
                ];
            }

            $denominator = $sectionLeaves * $traineeCount;
            $sectionRows[] = [
                'id' => $section->id,
                'title' => $section->title,
                'average_score' => isset($avgBySection[$section->id])
                    ? round((float) $avgBySection[$section->id], 1)
                    : null,
                'completion' => $denominator > 0
                    ? (int) round((int) ($doneBySection[$section->id] ?? 0) / $denominator * 100)
                    : 0,
            ];
        }

        return [
            'sections' => $sectionRows,
            'categories' => $categoryRows,
            'problem_items' => $this->problemItems($scope, $leafIds),
        ];
    }

    /**
     * Average score and completion grouped by item importance.
     *
     * @return array<int, array{importance: string|null, label: string, average_score: float|null, completion: int, evaluations: int}>
     */
    public function importanceBreakdown(ReportScope $scope): array
    {
        $traineeCount = count($scope->traineeIds);
        $leafIds = $this->progress->leafItemIds();

        $leafByImportance = ChecklistItem::query()
            ->whereDoesntHave('children')
            ->selectRaw('importance, count(*) as total')
            ->groupBy('importance')
            ->pluck('total', 'importance');

        $scored = $scope->isEmpty() ? [] : DB::table('evaluations')
            ->join('checklist_items', 'checklist_items.id', '=', 'evaluations.checklist_item_id')
            ->whereIn('evaluations.trainee_id', $scope->traineeIds)
            ->whereNotNull('rating')
            ->groupBy('checklist_items.importance')
            ->selectRaw('checklist_items.importance as importance, avg(rating) as average, count(*) as total')
            ->get()
            ->keyBy('importance')
            ->all();

        $done = $this->groupedCompleted($scope, 'checklist_items.importance', $leafIds);

        $keys = ['highly_important', 'moderately_important', 'optional', null];

        return array_map(function (?string $key) use ($scored, $done, $leafByImportance, $traineeCount): array {
            $stats = $scored[(string) $key] ?? null;
            $leaves = (int) ($leafByImportance[$key] ?? 0);
            $denominator = $leaves * $traineeCount;

            return [
                'importance' => $key,
                'label' => $key === null ? 'Unspecified' : Importance::from($key)->label(),
                'average_score' => $stats !== null && $stats->average !== null ? round((float) $stats->average, 1) : null,
                'completion' => $denominator > 0
                    ? (int) round((int) ($done[$key] ?? 0) / $denominator * 100)
                    : 0,
                'evaluations' => $stats !== null ? (int) $stats->total : 0,
            ];
        }, $keys);
    }

    /**
     * The global average rating across the scope (mean of scored items only).
     */
    private function averageScore(ReportScope $scope): ?float
    {
        if ($scope->isEmpty()) {
            return null;
        }

        $average = Evaluation::query()
            ->whereIn('trainee_id', $scope->traineeIds)
            ->whereNotNull('rating')
            ->avg('rating');

        return $average === null ? null : round((float) $average, 1);
    }

    /**
     * Lowest-scoring leaf items across the scope (min one rating).
     *
     * @param  Collection<int, int>  $leafIds
     * @return array<int, array{id: int, title: string, category_title: string, section_title: string, average_score: float, evaluations: int}>
     */
    private function problemItems(ReportScope $scope, $leafIds): array
    {
        if ($scope->isEmpty()) {
            return [];
        }

        $worst = DB::table('evaluations')
            ->whereIn('trainee_id', $scope->traineeIds)
            ->whereIn('checklist_item_id', $leafIds)
            ->whereNotNull('rating')
            ->groupBy('checklist_item_id')
            ->selectRaw('checklist_item_id, avg(rating) as average, count(*) as total')
            ->orderBy('average')
            ->limit(8)
            ->get();

        if ($worst->isEmpty()) {
            return [];
        }

        $items = ChecklistItem::query()
            ->whereIn('id', $worst->pluck('checklist_item_id'))
            ->with('category:id,section_id,title', 'category.section:id,title')
            ->get()
            ->keyBy('id');

        return $worst->map(function (object $row) use ($items): ?array {
            $item = $items->get($row->checklist_item_id);
            if (! $item) {
                return null;
            }

            return [
                'id' => $item->id,
                'title' => $item->title,
                'category_title' => $item->category->title,
                'section_title' => $item->category->section->title,
                'average_score' => round((float) $row->average, 1),
                'evaluations' => (int) $row->total,
            ];
        })->filter()->values()->all();
    }

    /**
     * Average rating grouped by an arbitrary column (optionally joining up to
     * categories for section-level grouping).
     *
     * @param  literal-string  $groupBy
     * @return Collection<int|string, float>
     */
    private function groupedAverage(ReportScope $scope, string $groupBy, bool $joinCategories = false)
    {
        if ($scope->isEmpty()) {
            return collect();
        }

        return Evaluation::query()
            ->join('checklist_items', 'checklist_items.id', '=', 'evaluations.checklist_item_id')
            ->when($joinCategories, fn ($query) => $query->join('categories', 'categories.id', '=', 'checklist_items.category_id'))
            ->whereIn('evaluations.trainee_id', $scope->traineeIds)
            ->whereNotNull('rating')
            ->groupBy($groupBy)
            ->selectRaw("{$groupBy} as bucket, avg(rating) as average")
            ->pluck('average', 'bucket');
    }

    /**
     * Completed leaf-evaluation counts grouped by an arbitrary column.
     *
     * @param  literal-string  $groupBy
     * @param  Collection<int, int>  $leafIds
     * @return Collection<int|string, int>
     */
    private function groupedCompleted(ReportScope $scope, string $groupBy, $leafIds, bool $joinCategories = false)
    {
        if ($scope->isEmpty()) {
            return collect();
        }

        return Evaluation::query()
            ->join('checklist_items', 'checklist_items.id', '=', 'evaluations.checklist_item_id')
            ->when($joinCategories, fn ($query) => $query->join('categories', 'categories.id', '=', 'checklist_items.category_id'))
            ->whereIn('evaluations.trainee_id', $scope->traineeIds)
            ->whereIn('checklist_item_id', $leafIds)
            ->where('completed', true)
            ->groupBy($groupBy)
            ->selectRaw("{$groupBy} as bucket, count(*) as total")
            ->pluck('total', 'bucket');
    }

    /**
     * Per-trainee status rows with the raw figures the public reports derive from.
     *
     * @return array<int, array{id: int, name: string, position: string|null, store: string, status: string, completion: int, completed_count: int, average_score: float|null, last_activity: string|null, onboarding_days: int|null}>
     */
    private function traineeRows(ReportScope $scope): array
    {
        if ($scope->isEmpty()) {
            return [];
        }

        $trainees = Trainee::query()
            ->whereIn('id', $scope->traineeIds)
            ->with('store:id,name')
            ->orderBy('name')
            ->get();

        $stats = $this->progress->rosterStats($scope->traineeIds);

        $lastActivity = Evaluation::query()
            ->whereIn('trainee_id', $scope->traineeIds)
            ->whereNotNull('completed_at')
            ->groupBy('trainee_id')
            ->selectRaw('trainee_id, max(completed_at) as last')
            ->pluck('last', 'trainee_id');

        $now = Carbon::now();

        return $trainees->map(function (Trainee $trainee) use ($stats, $lastActivity, $now): array {
            $completed = $stats[$trainee->id]['completed'] ?? 0;
            $total = $stats[$trainee->id]['total'] ?? 0;
            $average = $stats[$trainee->id]['average_rating'] ?? null;

            $last = $lastActivity[$trainee->id] ?? null;
            $lastCarbon = $last ? Carbon::parse($last) : null;
            // Staleness is measured from the last completion, else from when the
            // trainee entered tracking — so a freshly-added trainee gets a grace
            // period rather than being flagged the moment they exist.
            $reference = $lastCarbon ?? $trainee->created_at;
            $stale = $reference === null || $reference->lt($now->copy()->subDays(self::STALE_DAYS));

            $status = $this->status($completed, $total, $average, $stale);

            $onboarding = null;
            if ($status === 'completed' && $lastCarbon !== null) {
                $startedAt = $trainee->hired_at ?? $trainee->created_at;
                $onboarding = $startedAt ? (int) max(0, $startedAt->diffInDays($lastCarbon)) : null;
            }

            return [
                'id' => $trainee->id,
                'name' => $trainee->name,
                'position' => $trainee->position,
                'store' => $trainee->store->name,
                'status' => $status,
                'completion' => $total > 0 ? (int) round($completed / $total * 100) : 0,
                'completed_count' => $completed,
                'average_score' => $average,
                'last_activity' => $lastCarbon?->toDateString(),
                'onboarding_days' => $onboarding,
            ];
        })->all();
    }

    /**
     * Classify a trainee into one of the four status buckets.
     */
    private function status(int $completed, int $total, ?float $average, bool $stale): string
    {
        if ($total > 0 && $completed >= $total) {
            return 'completed';
        }

        if ($completed === 0 && ! $stale) {
            return 'not_started';
        }

        $lowScore = $average !== null && $average < self::AT_RISK_SCORE;

        return ($lowScore || $stale) ? 'at_risk' : 'in_progress';
    }
}
