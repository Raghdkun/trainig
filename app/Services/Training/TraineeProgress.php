<?php

namespace App\Services\Training;

use App\Models\Category;
use App\Models\ChecklistItem;
use App\Models\Evaluation;
use App\Models\QuizAttempt;
use App\Models\Section;
use App\Models\Trainee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TraineeProgress
{
    /** @var Collection<int, int>|null */
    private ?Collection $leafIds = null;

    /**
     * IDs of leaf checklist items (those with no sub-items) in published
     * stations. Only leaves count toward completion, so a parent never
     * double-counts with its children; draft stations are excluded so their
     * unfinished content never affects completion totals or reports.
     *
     * @return Collection<int, int>
     */
    public function leafItemIds(): Collection
    {
        return $this->leafIds ??= ChecklistItem::query()
            ->whereDoesntHave('children')
            ->whereHas('category.section', fn ($query) => $query->published())
            ->pluck('id');
    }

    /**
     * Completion + average rating per trainee, keyed by trainee id.
     *
     * @param  Collection<int, int>|array<int, int>  $traineeIds
     * @return array<int, array{completed: int, total: int, average_rating: float|null}>
     */
    public function rosterStats(Collection|array $traineeIds): array
    {
        $ids = collect($traineeIds);
        $leafIds = $this->leafItemIds();
        $total = $leafIds->count();

        $completed = Evaluation::query()
            ->whereIn('trainee_id', $ids)
            ->where('completed', true)
            ->whereIn('checklist_item_id', $leafIds)
            ->selectRaw('trainee_id, count(*) as aggregate')
            ->groupBy('trainee_id')
            ->pluck('aggregate', 'trainee_id');

        $averages = Evaluation::query()
            ->whereIn('trainee_id', $ids)
            ->whereNotNull('rating')
            ->selectRaw('trainee_id, avg(rating) as aggregate')
            ->groupBy('trainee_id')
            ->pluck('aggregate', 'trainee_id');

        $stats = [];
        foreach ($ids as $id) {
            $stats[$id] = [
                'completed' => (int) ($completed[$id] ?? 0),
                'total' => $total,
                'average_rating' => isset($averages[$id])
                    ? round((float) $averages[$id], 1)
                    : null,
            ];
        }

        return $stats;
    }

    /**
     * The full section tree with each item's evaluation merged in, plus the
     * current step (first incomplete leaf in order) and headline stats.
     *
     * @return array{sections: array<int, mixed>, currentStepId: int|null, stats: array{completed: int, total: int, average_rating: float|null}}
     */
    public function detail(Trainee $trainee): array
    {
        $sections = Section::ordered()->published()->with([
            'categories' => fn ($query) => $query->orderBy('order'),
            'categories.items.media',
            // Recursive so arbitrarily deep sub-items don't cost a query each.
            'categories.items.childrenRecursive',
            'quiz.questions',
        ])->get();

        $evaluations = $trainee->evaluations()->get()->keyBy('checklist_item_id');
        $quizAttempts = $trainee->quizAttempts()->get()->keyBy('quiz_id');
        $currentStepId = null;

        $mapItem = function (ChecklistItem $item) use (&$mapItem, $evaluations, &$currentStepId): array {
            $children = $item->childrenRecursive->map($mapItem)->all();
            $evaluation = $evaluations->get($item->id);
            $completed = (bool) $evaluation?->completed;

            if (count($children) === 0 && ! $completed && $currentStepId === null) {
                $currentStepId = $item->id;
            }

            return [
                'id' => $item->id,
                'category_id' => $item->category_id,
                'parent_id' => $item->parent_id,
                'title' => $item->title,
                'content' => $item->content,
                'importance' => $item->importance,
                'requires_rating' => $item->requires_rating,
                'order' => $item->order,
                'media' => $item->media,
                'children' => $children,
                'evaluation' => $evaluation ? [
                    'completed' => (bool) $evaluation->completed,
                    'rating' => $evaluation->rating,
                    'notes' => $evaluation->notes,
                ] : null,
            ];
        };

        // Collect the non-null scores under a set of items (recursing sub-items).
        $collectRatings = function ($items) use (&$collectRatings, $evaluations): array {
            $ratings = [];
            foreach ($items as $item) {
                $evaluation = $evaluations->get($item->id);
                if ($evaluation && $evaluation->rating !== null) {
                    $ratings[] = (int) $evaluation->rating;
                }
                $ratings = array_merge($ratings, $collectRatings($item->childrenRecursive));
            }

            return $ratings;
        };

        $average = fn (array $ratings): ?float => $ratings === []
            ? null
            : round(array_sum($ratings) / count($ratings), 1);

        $sectionsData = $sections->map(function (Section $section) use ($mapItem, $collectRatings, $average, $quizAttempts): array {
            $sectionRatings = [];

            $categories = $section->categories->map(function (Category $category) use ($mapItem, $collectRatings, $average, &$sectionRatings): array {
                $categoryRatings = $collectRatings($category->items);
                $sectionRatings = array_merge($sectionRatings, $categoryRatings);

                return [
                    'id' => $category->id,
                    'title' => $category->title,
                    'description' => $category->description,
                    'color' => $category->color,
                    'average_rating' => $average($categoryRatings),
                    'items' => $category->items->map($mapItem)->all(),
                ];
            })->all();

            return [
                'id' => $section->id,
                'title' => $section->title,
                'description' => $section->description,
                'icon' => $section->icon,
                'pie_content_review' => $section->pie_content_review,
                'screen_to_shoulder' => $section->screen_to_shoulder,
                'hands_on_shifts' => $section->hands_on_shifts,
                'average_rating' => $average($sectionRatings),
                'categories' => $categories,
                'quiz' => $this->quizStatus($section, $quizAttempts),
            ];
        })->all();

        return [
            'sections' => $sectionsData,
            'currentStepId' => $currentStepId,
            'stats' => $this->rosterStats([$trainee->id])[$trainee->id],
        ];
    }

    /**
     * A section's quiz status for the trainee page — safe for any viewer
     * (assigned manager or super admin) since it never includes the score or
     * answers, only whether it's been sent/completed and the shareable link
     * while still pending. Full results live only in the training-team-only
     * Quiz Results view.
     *
     * @param  Collection<int, QuizAttempt>  $attemptsByQuizId
     * @return array{id: int, questions_count: int, attempt: array{status: string, link: string|null, flagged: bool}|null}|null
     */
    private function quizStatus(Section $section, Collection $attemptsByQuizId): ?array
    {
        $quiz = $section->quiz;

        if (! $quiz) {
            return null;
        }

        $attempt = $attemptsByQuizId->get($quiz->id);

        return [
            'id' => $quiz->id,
            'questions_count' => $quiz->questions->count(),
            'attempt' => $attempt ? [
                'status' => $attempt->isCompleted() ? 'completed' : 'sent',
                'link' => $attempt->isCompleted() ? null : route('quiz.show', $attempt->token),
                'flagged' => $attempt->isFlaggedAsMisdirected(),
            ] : null,
        ];
    }

    /**
     * Minimal Section → Category → leaf-item tree (published stations only,
     * no evaluation data) for the Development Plan picker's checkbox list.
     *
     * @return array<int, array{id: int, title: string, categories: array<int, array{id: int, title: string, items: array<int, array{id: int, title: string}>}>}>
     */
    public function pickerTree(): array
    {
        $sections = Section::ordered()->published()->with([
            'categories' => fn ($query) => $query->orderBy('order'),
            'categories.checklistItems' => fn ($query) => $query
                ->whereDoesntHave('children')
                ->orderBy('order'),
        ])->get();

        return $sections->map(fn (Section $section): array => [
            'id' => $section->id,
            'title' => $section->title,
            'categories' => $section->categories->map(fn (Category $category): array => [
                'id' => $category->id,
                'title' => $category->title,
                'items' => $category->checklistItems->map(fn (ChecklistItem $item): array => [
                    'id' => $item->id,
                    'title' => $item->title,
                ])->all(),
            ])->all(),
        ])->all();
    }

    /**
     * A trainee's curated Development Plan, grouped into the same
     * Section → Category → item tree shape as `detail()` (so the frontend
     * can render it with the exact same section/category UI as the standard
     * checklist) but containing only this trainee's picked items, plus
     * completion within just this subset.
     *
     * @return array{sections: array<int, mixed>, stats: array{completed: int, total: int}}
     */
    public function developmentPlan(Trainee $trainee): array
    {
        $items = $trainee->developmentItems()
            ->with(['media', 'category.section'])
            ->orderBy('checklist_items.order')
            ->get();

        $evaluations = $trainee->evaluations()
            ->whereIn('checklist_item_id', $items->pluck('id'))
            ->get()
            ->keyBy('checklist_item_id');

        $mapItem = fn (ChecklistItem $item): array => [
            'id' => $item->id,
            'category_id' => $item->category_id,
            'parent_id' => $item->parent_id,
            'title' => $item->title,
            'content' => $item->content,
            'importance' => $item->importance,
            'requires_rating' => $item->requires_rating,
            'order' => $item->order,
            'media' => $item->media,
            'children' => [],
            'evaluation' => ($evaluation = $evaluations->get($item->id)) ? [
                'completed' => (bool) $evaluation->completed,
                'rating' => $evaluation->rating,
                'notes' => $evaluation->notes,
            ] : null,
        ];

        // Average of only the rated items among the given set — same
        // approach as detail()'s collectRatings, just scoped to a flat list
        // since plan items are always leaves.
        $average = function (Collection $categoryItems) use ($evaluations): ?float {
            $ratings = $categoryItems
                ->map(fn (ChecklistItem $item) => $evaluations->get($item->id)?->rating)
                ->filter(fn (?int $rating): bool => $rating !== null);

            return $ratings->isEmpty() ? null : round($ratings->average(), 1);
        };

        $sections = [];

        foreach ($items->groupBy(fn (ChecklistItem $item) => $item->category->section_id) as $sectionItems) {
            $section = $sectionItems->first()->category->section;
            $categories = [];

            foreach ($sectionItems->groupBy('category_id') as $categoryItems) {
                $category = $categoryItems->first()->category;

                $categories[] = [
                    'order' => $category->order,
                    'data' => [
                        'id' => $category->id,
                        'title' => $category->title,
                        'description' => $category->description,
                        'color' => $category->color,
                        'average_rating' => $average($categoryItems),
                        'items' => $categoryItems->sortBy('order')->map($mapItem)->values()->all(),
                    ],
                ];
            }

            usort($categories, fn (array $a, array $b): int => $a['order'] <=> $b['order']);

            $sections[] = [
                'order' => $section->order,
                'data' => [
                    'id' => $section->id,
                    'title' => $section->title,
                    'description' => $section->description,
                    'icon' => $section->icon,
                    'pie_content_review' => $section->pie_content_review,
                    'screen_to_shoulder' => $section->screen_to_shoulder,
                    'hands_on_shifts' => $section->hands_on_shifts,
                    'average_rating' => $average($sectionItems),
                    'categories' => array_map(fn (array $c): array => $c['data'], $categories),
                    'quiz' => null,
                ],
            ];
        }

        usort($sections, fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return [
            'sections' => array_map(fn (array $s): array => $s['data'], $sections),
            'stats' => [
                'completed' => $evaluations->filter(fn (Evaluation $e): bool => (bool) $e->completed)->count(),
                'total' => $items->count(),
            ],
        ];
    }

    /**
     * Per-trainee completed/total within EACH trainee's own Development Plan
     * (unlike `rosterStats()`, "total" varies per trainee — everyone's plan
     * is a different curated subset). Used by the Dashboard's Development
     * Zone panel.
     *
     * @param  Collection<int, int>|array<int, int>  $traineeIds
     * @return array<int, array{completed: int, total: int}>
     */
    public function developmentStats(Collection|array $traineeIds): array
    {
        $ids = collect($traineeIds);

        if ($ids->isEmpty()) {
            return [];
        }

        $totals = DB::table('development_plan_items')
            ->whereIn('trainee_id', $ids)
            ->selectRaw('trainee_id, count(*) as aggregate')
            ->groupBy('trainee_id')
            ->pluck('aggregate', 'trainee_id');

        $completed = DB::table('development_plan_items')
            ->join('evaluations', function ($join): void {
                $join->on('evaluations.trainee_id', '=', 'development_plan_items.trainee_id')
                    ->on('evaluations.checklist_item_id', '=', 'development_plan_items.checklist_item_id');
            })
            ->whereIn('development_plan_items.trainee_id', $ids)
            ->where('evaluations.completed', true)
            ->selectRaw('development_plan_items.trainee_id as trainee_id, count(*) as aggregate')
            ->groupBy('development_plan_items.trainee_id')
            ->pluck('aggregate', 'trainee_id');

        $stats = [];
        foreach ($ids as $id) {
            $stats[$id] = [
                'completed' => (int) ($completed[$id] ?? 0),
                'total' => (int) ($totals[$id] ?? 0),
            ];
        }

        return $stats;
    }
}
