import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { QuizAttemptStatus } from '@/types/training';

const TEXT_STYLES: Record<QuizAttemptStatus, string> = {
    sent: 'text-amber-600 dark:text-amber-400',
    completed: 'text-emerald-600 dark:text-emerald-400',
};

const LABELS: Record<QuizAttemptStatus, string> = {
    sent: 'Sent',
    completed: 'Completed',
};

/**
 * A quiet outline chip for a quiz attempt's status, matching
 * SectionStatusBadge's colored-text-only treatment.
 */
export function QuizStatusBadge({
    status,
    className,
}: {
    status: QuizAttemptStatus;
    className?: string;
}) {
    return (
        <Badge variant="outline" className={cn(TEXT_STYLES[status], className)}>
            {LABELS[status]}
        </Badge>
    );
}
