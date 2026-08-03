import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { Importance } from '@/types/training';

const STYLES: Record<Importance, string> = {
    highly_important:
        'border-transparent bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
    moderately_important:
        'border-transparent bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    optional: 'border-transparent bg-muted text-muted-foreground',
};

const LABELS: Record<Importance, string> = {
    highly_important: 'Highly important',
    moderately_important: 'Moderately important',
    optional: 'Optional',
};

export function ImportanceBadge({
    importance,
    className,
}: {
    importance: Importance | null;
    className?: string;
}) {
    if (!importance) {
        return null;
    }

    return (
        <Badge variant="outline" className={cn(STYLES[importance], className)}>
            {LABELS[importance]}
        </Badge>
    );
}
