import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { SectionStatus } from '@/types/training';

const TEXT_STYLES: Record<SectionStatus, string> = {
    draft: 'text-muted-foreground',
    published: 'text-emerald-600 dark:text-emerald-400',
};

const LABELS: Record<SectionStatus, string> = {
    draft: 'Draft',
    published: 'Published',
};

/**
 * A quiet outline chip that sits with the other meta badges (categories,
 * items, hands-on) rather than crowding the title.
 */
export function SectionStatusBadge({
    status,
    className,
}: {
    status: SectionStatus;
    className?: string;
}) {
    return (
        <Badge variant="outline" className={cn(TEXT_STYLES[status], className)}>
            {LABELS[status]}
        </Badge>
    );
}
