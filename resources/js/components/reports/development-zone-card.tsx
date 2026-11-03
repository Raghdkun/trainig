import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { ReportCard, ReportEmpty } from '@/components/reports/report-card';
import { CompletionBar } from '@/components/training/completion-bar';
import { Button } from '@/components/ui/button';
import { index as developmentZoneIndex } from '@/routes/development-zone';
import type { DevelopmentZoneSummary } from '@/types/reports';

const STATUS_TILES: {
    key: keyof Pick<DevelopmentZoneSummary, 'pending' | 'active' | 'completed'>;
    label: string;
    textClass: string;
}[] = [
    {
        key: 'pending',
        label: 'Pending',
        textClass: 'text-amber-600 dark:text-amber-400',
    },
    {
        key: 'active',
        label: 'Active',
        textClass: 'text-blue-600 dark:text-blue-400',
    },
    {
        key: 'completed',
        label: 'Completed',
        textClass: 'text-emerald-600 dark:text-emerald-400',
    },
];

export function DevelopmentZoneCard({
    summary,
}: {
    summary?: DevelopmentZoneSummary;
}) {
    return (
        <ReportCard
            title="Development Zone"
            description="Trainees evaluated and placed on an individualized plan"
            action={
                <Button variant="outline" size="sm" asChild>
                    <Link href={developmentZoneIndex().url}>
                        View all <ArrowRight className="size-4" />
                    </Link>
                </Button>
            }
        >
            {!summary || summary.in_zone === 0 ? (
                <ReportEmpty message="No one is in the Development Zone right now." />
            ) : (
                <div className="space-y-4">
                    <div className="grid grid-cols-3 gap-3">
                        {STATUS_TILES.map((tile) => (
                            <div
                                key={tile.key}
                                className="rounded-lg border p-3 text-center"
                            >
                                <p
                                    className={`text-2xl font-semibold tabular-nums ${tile.textClass}`}
                                >
                                    {summary[tile.key]}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {tile.label}
                                </p>
                            </div>
                        ))}
                    </div>
                    <div className="space-y-1">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span>Development plan completion</span>
                            <span>
                                Avg evaluation:{' '}
                                {summary.average_evaluation_rating !== null
                                    ? `${summary.average_evaluation_rating}/5`
                                    : '—'}
                            </span>
                        </div>
                        <CompletionBar
                            completed={summary.plan_completion}
                            total={100}
                        />
                    </div>
                </div>
            )}
        </ReportCard>
    );
}
