import { ArrowRight, ClipboardCheck, Target } from 'lucide-react';
import { LocalTime } from '@/components/local-time';
import {
    DEVELOPMENT_NEED_HINT,
    StarMeter,
    formatStars,
} from '@/components/training/star-meter';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { AssessmentHistoryEntry, SkillRating } from '@/types/training';

/**
 * The employee's current level at each station/skill from their latest
 * assessment. Stations under 3★ are Development Needs; after a reassessment
 * each row shows before → after, so improvement is visible at a glance.
 */
export function SkillRatingsCard({
    ratings,
    history,
}: {
    ratings: SkillRating[];
    history: AssessmentHistoryEntry[];
}) {
    const latest = history.at(-1);
    const needsCount = ratings.filter((rating) => rating.is_need).length;
    const reassessed = ratings.some((rating) => rating.baseline_stars !== null);

    return (
        <Card className="gap-0 p-0">
            <div className="flex flex-wrap items-start justify-between gap-3 border-b border-border/60 p-5">
                <div>
                    <h2 className="font-semibold tracking-tight">
                        Stations & skills
                    </h2>
                    {latest && (
                        <p className="text-sm text-muted-foreground">
                            {latest.is_reassessment
                                ? 'Reassessed'
                                : 'From the manager’s evaluation'}
                            {latest.evaluator
                                ? ` by ${latest.evaluator.name}`
                                : ''}{' '}
                            ·{' '}
                            <LocalTime
                                iso={latest.submitted_at}
                                variant="relative"
                            />
                        </p>
                    )}
                </div>
                {needsCount > 0 && (
                    <Badge
                        variant="outline"
                        className="gap-1 text-muted-foreground"
                    >
                        <Target className="size-3 text-amber-500" />
                        {needsCount} development{' '}
                        {needsCount === 1 ? 'need' : 'needs'}
                    </Badge>
                )}
            </div>

            {ratings.length === 0 ? (
                <div className="flex flex-col items-center gap-2 p-8 text-center">
                    <ClipboardCheck className="size-7 text-muted-foreground" />
                    <p className="text-sm text-muted-foreground">
                        No assessment on file yet.
                    </p>
                </div>
            ) : (
                <ul className="divide-y divide-border/60">
                    {ratings.map((rating) => (
                        <li
                            key={rating.skill_id ?? rating.name}
                            className={cn(
                                'flex flex-col gap-2 px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between sm:gap-4',
                            )}
                        >
                            <div className="flex min-w-0 items-center gap-2">
                                <span className="truncate font-medium">
                                    {rating.name}
                                </span>
                                {rating.is_need && (
                                    <Badge
                                        variant="outline"
                                        className="shrink-0 gap-1 text-muted-foreground"
                                        title={DEVELOPMENT_NEED_HINT}
                                    >
                                        <Target className="size-3 text-amber-500" />
                                        Development need
                                    </Badge>
                                )}
                            </div>
                            <div className="flex items-center gap-3">
                                {rating.baseline_stars !== null && (
                                    <>
                                        <StarMeter
                                            value={rating.baseline_stars}
                                            size="sm"
                                            className="opacity-60"
                                        />
                                        <ArrowRight className="size-3.5 text-muted-foreground" />
                                    </>
                                )}
                                <StarMeter value={rating.stars} showValue />
                                {rating.baseline_stars !== null && (
                                    <Change
                                        from={rating.baseline_stars}
                                        to={rating.stars}
                                    />
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {history.length > 0 && (
                <div className="border-t border-border/60 p-5">
                    <p className="mb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Assessment history
                    </p>
                    <ol className="space-y-2">
                        {history.map((entry) => (
                            <li
                                key={entry.id}
                                className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5 text-sm"
                            >
                                <span className="font-medium">
                                    {entry.is_reassessment
                                        ? 'Reassessment'
                                        : 'Initial evaluation'}
                                </span>
                                <span className="text-muted-foreground">
                                    {entry.evaluator
                                        ? `by ${entry.evaluator.name} · `
                                        : ''}
                                    <LocalTime
                                        iso={entry.submitted_at}
                                        variant="datetime"
                                    />
                                </span>
                                {entry.average_stars !== null && (
                                    <span className="text-muted-foreground tabular-nums">
                                        · average{' '}
                                        {formatStars(entry.average_stars)}★
                                    </span>
                                )}
                                {entry.notes && (
                                    <p className="w-full whitespace-pre-line text-muted-foreground">
                                        “{entry.notes}”
                                    </p>
                                )}
                            </li>
                        ))}
                    </ol>
                    {!reassessed && history.length === 1 && (
                        <p className="mt-2 text-xs text-muted-foreground">
                            A reassessment by the training team will show before
                            → after for each station.
                        </p>
                    )}
                </div>
            )}
        </Card>
    );
}

function Change({ from, to }: { from: number; to: number }) {
    const delta = to - from;

    if (delta === 0) {
        return (
            <span className="w-14 text-right text-xs text-muted-foreground">
                no change
            </span>
        );
    }

    return (
        <span
            className={cn(
                'w-14 text-right text-xs font-semibold tabular-nums',
                delta > 0
                    ? 'text-emerald-600 dark:text-emerald-400'
                    : 'text-destructive',
            )}
        >
            {delta > 0 ? '+' : '−'}
            {formatStars(Math.abs(delta))}★
        </span>
    );
}
