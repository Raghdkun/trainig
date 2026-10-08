import { ChevronDown } from 'lucide-react';
import { EvaluationItem } from '@/components/training/evaluation-item';
import { RatingMeter } from '@/components/training/rating-meter';
import { Badge } from '@/components/ui/badge';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { categoryColorClasses } from '@/lib/category-colors';
import { cn } from '@/lib/utils';
import type {
    EvaluationItem as EvaluationItemType,
    ProgressCategory,
} from '@/types/training';

function leafCount(items: EvaluationItemType[]): {
    done: number;
    total: number;
} {
    return items.reduce(
        (acc, item) => {
            if (item.children.length > 0) {
                const child = leafCount(item.children);

                return {
                    done: acc.done + child.done,
                    total: acc.total + child.total,
                };
            }

            return {
                done: acc.done + (item.evaluation?.completed ? 1 : 0),
                total: acc.total + 1,
            };
        },
        { done: 0, total: 0 },
    );
}

export function CategorySection({
    category,
    traineeId,
    currentStepId,
    readOnly = false,
    open,
    onOpenChange,
}: {
    category: ProgressCategory;
    traineeId: number;
    currentStepId: number | null;
    readOnly?: boolean;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const colors = categoryColorClasses(category.color);
    const count = leafCount(category.items);
    const allDone = count.total > 0 && count.done === count.total;

    return (
        <Collapsible
            open={open}
            onOpenChange={onOpenChange}
            className={cn(
                'overflow-hidden rounded-lg border',
                colors && 'border-l-4',
                colors?.border,
            )}
        >
            <CollapsibleTrigger
                className={cn(
                    'group flex w-full items-center gap-3 p-3 text-left transition-colors hover:bg-muted/40',
                    colors?.tint,
                )}
            >
                <ChevronDown className="size-4 shrink-0 text-muted-foreground transition-transform group-data-[state=closed]:-rotate-90" />
                {colors && (
                    <span
                        className={cn(
                            'size-2.5 shrink-0 rounded-full',
                            colors.dot,
                        )}
                    />
                )}
                <span className="flex-1 truncate font-medium">
                    {category.title}
                </span>
                {category.average_rating !== null && (
                    <RatingMeter
                        value={category.average_rating}
                        size="sm"
                        className="hidden shrink-0 sm:flex"
                    />
                )}
                <Badge variant={allDone ? 'default' : 'secondary'}>
                    {count.done}/{count.total}
                </Badge>
            </CollapsibleTrigger>
            <CollapsibleContent className="space-y-2 p-3">
                {category.items.map((item) => (
                    <EvaluationItem
                        key={item.id}
                        item={item}
                        traineeId={traineeId}
                        currentStepId={currentStepId}
                        readOnly={readOnly}
                    />
                ))}
            </CollapsibleContent>
        </Collapsible>
    );
}
