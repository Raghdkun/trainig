import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { DEVELOPMENT_STATUS_LABELS } from '@/types/training';
import type { DevelopmentStatus } from '@/types/training';

const TEXT_STYLES: Record<DevelopmentStatus, string> = {
    pending: 'text-amber-600 dark:text-amber-400',
    active: 'text-blue-600 dark:text-blue-400',
    completed: 'text-emerald-600 dark:text-emerald-400',
};

/**
 * A quiet outline chip showing a trainee's place in the Development Zone
 * lifecycle (Pending → Active → Completed).
 */
export function DevelopmentStatusBadge({
    status,
    className,
}: {
    status: DevelopmentStatus;
    className?: string;
}) {
    return (
        <Badge variant="outline" className={cn(TEXT_STYLES[status], className)}>
            {DEVELOPMENT_STATUS_LABELS[status]}
        </Badge>
    );
}
