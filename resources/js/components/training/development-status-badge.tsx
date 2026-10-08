import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { DEVELOPMENT_STATUS_LABELS } from '@/types/training';
import type { DevelopmentStatus } from '@/types/training';

const DOT_STYLES: Record<DevelopmentStatus, string> = {
    pending: 'bg-amber-500',
    active: 'bg-blue-500',
    completed: 'bg-emerald-500',
};

/**
 * A quiet outline chip showing a trainee's place in the Development Zone
 * lifecycle (Pending → Active → Completed). Only the dot carries color.
 */
export function DevelopmentStatusBadge({
    status,
    className,
}: {
    status: DevelopmentStatus;
    className?: string;
}) {
    return (
        <Badge
            variant="outline"
            className={cn('gap-1.5 text-muted-foreground', className)}
        >
            <span
                aria-hidden
                className={cn('size-1.5 rounded-full', DOT_STYLES[status])}
            />
            {DEVELOPMENT_STATUS_LABELS[status]}
        </Badge>
    );
}
