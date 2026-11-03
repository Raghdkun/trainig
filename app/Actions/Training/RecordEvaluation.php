<?php

namespace App\Actions\Training;

use App\Models\ChecklistItem;
use App\Models\Evaluation;
use App\Models\Trainee;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RecordEvaluation
{
    /**
     * Record a manager's evaluation of a single checklist item for a trainee,
     * cascading completion down to sub-items and recomputing the parent.
     *
     * @param  array{completed: bool, rating?: int|null, notes?: string|null}  $data
     */
    public function handle(Trainee $trainee, ChecklistItem $item, array $data, User $evaluator): void
    {
        DB::transaction(function () use ($trainee, $item, $data, $evaluator): void {
            $completed = (bool) $data['completed'];
            $completedAt = $completed ? now() : null;

            // 1. The item itself (carries rating + notes).
            $this->upsert($trainee, $item->id, [
                'completed' => $completed,
                'rating' => $data['rating'] ?? null,
                'notes' => $data['notes'] ?? null,
                'evaluated_by' => $evaluator->id,
                'completed_at' => $completedAt,
            ]);

            // 2. Down-cascade to every descendant, not just direct children —
            //    sub-items may nest deeper (preserve their rating/notes).
            foreach ($this->descendantIds($item->id) as $descendantId) {
                $this->upsert($trainee, $descendantId, [
                    'completed' => $completed,
                    'evaluated_by' => $evaluator->id,
                    'completed_at' => $completedAt,
                ]);
            }

            // 3. Up-cascade the whole ancestor chain: a parent is complete only
            //    when all of its children are, and that can change a grandparent.
            $this->recomputeAncestors($trainee, $item->parent_id, $evaluator);
        });
    }

    /**
     * Every descendant id beneath an item, walked level by level.
     *
     * @return array<int, int>
     */
    private function descendantIds(int $itemId): array
    {
        $collected = [];
        $frontier = [$itemId];

        while (true) {
            $children = ChecklistItem::whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->reject(fn (int $id): bool => in_array($id, $collected, true))
                ->values()
                ->all();

            if ($children === []) {
                return $collected;
            }

            $collected = array_merge($collected, $children);
            $frontier = $children;
        }
    }

    /**
     * Recompute completion for each ancestor, bottom-up.
     */
    private function recomputeAncestors(Trainee $trainee, ?int $parentId, User $evaluator): void
    {
        $seen = [];

        while ($parentId !== null && ! in_array($parentId, $seen, true)) {
            $seen[] = $parentId;

            $this->recomputeParent($trainee, $parentId, $evaluator);

            $next = ChecklistItem::whereKey($parentId)->value('parent_id');
            $parentId = $next !== null ? (int) $next : null;
        }
    }

    private function recomputeParent(Trainee $trainee, int $parentId, User $evaluator): void
    {
        $children = ChecklistItem::where('parent_id', $parentId)->get(['id', 'counts_toward_progress']);

        // An optional sub-step never holds its parent back — unless every
        // sub-step is optional, in which case they all have to be done.
        $required = $children->where('counts_toward_progress', true);
        $childIds = ($required->isNotEmpty() ? $required : $children)->pluck('id');

        $completedChildren = Evaluation::where('trainee_id', $trainee->id)
            ->whereIn('checklist_item_id', $childIds)
            ->where('completed', true)
            ->count();

        $allComplete = $childIds->isNotEmpty() && $completedChildren === $childIds->count();

        $this->upsert($trainee, $parentId, [
            'completed' => $allComplete,
            'evaluated_by' => $evaluator->id,
            'completed_at' => $allComplete ? now() : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsert(Trainee $trainee, int $checklistItemId, array $attributes): void
    {
        Evaluation::updateOrCreate(
            ['trainee_id' => $trainee->id, 'checklist_item_id' => $checklistItemId],
            $attributes,
        );
    }
}
