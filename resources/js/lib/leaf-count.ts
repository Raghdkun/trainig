import type { EvaluationItem } from '@/types/training';

/**
 * Done/total leaf steps under a set of items. Optional steps (counts_toward_progress
 * off) are skipped — they aren't counted toward completion — unless the whole
 * station is optional, where its own progress is still worth showing.
 */
export function leafCount(
    items: EvaluationItem[],
    includeOptional: boolean,
): { done: number; total: number } {
    return items.reduce(
        (acc, item) => {
            if (item.children.length > 0) {
                const child = leafCount(item.children, includeOptional);

                return {
                    done: acc.done + child.done,
                    total: acc.total + child.total,
                };
            }

            if (!includeOptional && !item.counts_toward_progress) {
                return acc;
            }

            return {
                done: acc.done + (item.evaluation?.completed ? 1 : 0),
                total: acc.total + 1,
            };
        },
        { done: 0, total: 0 },
    );
}
