<?php

namespace App\Services\Training;

use App\Models\Category;
use App\Models\ChecklistItem;
use App\Models\Evaluation;
use App\Models\Section;
use App\Models\Trainee;
use Illuminate\Support\Collection;

class TraineeProgress
{
    /** @var Collection<int, int>|null */
    private ?Collection $leafIds = null;

    /**
     * IDs of leaf checklist items (those with no sub-items). Only leaves count
     * toward completion, so a parent never double-counts with its children.
     *
     * @return Collection<int, int>
     */
    public function leafItemIds(): Collection
    {
        return $this->leafIds ??= ChecklistItem::query()
            ->whereDoesntHave('children')
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
        $sections = Section::ordered()->with([
            'categories' => fn ($query) => $query->orderBy('order'),
            'categories.items.media',
            // Recursive so arbitrarily deep sub-items don't cost a query each.
            'categories.items.childrenRecursive',
        ])->get();

        $evaluations = $trainee->evaluations()->get()->keyBy('checklist_item_id');
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

        $sectionsData = $sections->map(function (Section $section) use ($mapItem, $collectRatings, $average): array {
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
            ];
        })->all();

        return [
            'sections' => $sectionsData,
            'currentStepId' => $currentStepId,
            'stats' => $this->rosterStats([$trainee->id])[$trainee->id],
        ];
    }
}
