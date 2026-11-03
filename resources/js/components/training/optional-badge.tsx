import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

/**
 * Marks a station or item that can still be worked through but is left out
 * of the completion count, average score and reports (counts_toward_progress
 * is off). Named "not counted" so it isn't confused with the Optional
 * importance level.
 */
export function OptionalBadge({ className }: { className?: string }) {
    return (
        <Badge
            variant="outline"
            title="Optional: not counted in completion, score or reports"
            className={cn(
                'shrink-0 border-amber-500/40 text-amber-600 dark:text-amber-400',
                className,
            )}
        >
            <span className="sm:hidden">Not counted</span>
            <span className="hidden sm:inline">Optional · not counted</span>
        </Badge>
    );
}
