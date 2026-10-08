import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { QuizAttemptStatus } from '@/types/training';

const TEXT_STYLES: Record<QuizAttemptStatus, string> = {
    not_started: 'text-muted-foreground',
    in_progress: 'text-amber-600 dark:text-amber-400',
    completed: 'text-emerald-600 dark:text-emerald-400',
};

const DOT_STYLES: Record<QuizAttemptStatus, string> = {
    not_started: 'bg-muted-foreground/50',
    in_progress: 'bg-amber-500 animate-pulse',
    completed: 'bg-emerald-500',
};

export const QUIZ_STATUS_LABELS: Record<QuizAttemptStatus, string> = {
    not_started: 'Not started',
    in_progress: 'In progress',
    completed: 'Completed',
};

/**
 * A quiet outline chip for a quiz attempt's status, matching
 * SectionStatusBadge's colored-text-only treatment, with a status dot so
 * the three states stay distinguishable at a glance.
 */
export function QuizStatusBadge({
    status,
    className,
}: {
    status: QuizAttemptStatus;
    className?: string;
}) {
    return (
        <Badge
            variant="outline"
            className={cn('gap-1.5', TEXT_STYLES[status], className)}
        >
            <span
                aria-hidden
                className={cn('size-1.5 rounded-full', DOT_STYLES[status])}
            />
            {QUIZ_STATUS_LABELS[status]}
        </Badge>
    );
}
