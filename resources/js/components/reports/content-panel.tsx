import { Deferred } from '@inertiajs/react';
import { QuizSummaryCard } from '@/components/reports/quiz-summary-card';
import { RankingBar } from '@/components/reports/ranking-bar';
import { ReportCard, ReportEmpty } from '@/components/reports/report-card';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type {
    ImportanceRow,
    QuizResultsSummary,
    StationInsights,
} from '@/types/reports';

function PanelSkeleton() {
    return (
        <div className="space-y-4">
            <div className="grid gap-4 lg:grid-cols-2">
                {[0, 1].map((i) => (
                    <div key={i} className="surface-tray">
                        <div className="surface-core space-y-3 p-5">
                            <Skeleton className="h-4 w-40" />
                            <Skeleton className="h-40 w-full" />
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

/** Weakest scores first; stations with no ratings sink to the bottom. */
function byWeakestScore<T extends { average_score: number | null }>(
    a: T,
    b: T,
): number {
    if (a.average_score === null) {
        return 1;
    }

    if (b.average_score === null) {
        return -1;
    }

    return a.average_score - b.average_score;
}

export function ContentPanel({
    insights,
    importance,
    quiz,
}: {
    insights?: StationInsights;
    importance?: ImportanceRow[];
    quiz?: QuizResultsSummary;
}) {
    const sections = [...(insights?.sections ?? [])].sort(byWeakestScore);
    const categories = [...(insights?.categories ?? [])]
        .sort(byWeakestScore)
        .slice(0, 8);
    const problems = insights?.problem_items ?? [];

    return (
        <Deferred
            data={['stationInsights', 'importanceBreakdown']}
            fallback={<PanelSkeleton />}
        >
            <div className="space-y-4">
                {quiz && <QuizSummaryCard summary={quiz} />}

                <div className="grid gap-4 lg:grid-cols-2">
                    <ReportCard
                        title="Station scores"
                        description="Weakest stations first"
                    >
                        {sections.length === 0 ? (
                            <ReportEmpty message="No station data yet." />
                        ) : (
                            <div className="space-y-3">
                                {sections.map((section) => (
                                    <RankingBar
                                        key={section.id}
                                        label={section.title}
                                        sublabel={`${section.completion}% complete`}
                                        value={section.average_score}
                                    />
                                ))}
                            </div>
                        )}
                    </ReportCard>

                    <ReportCard
                        title="By importance"
                        description="Performance on each priority level"
                    >
                        {!importance || importance.length === 0 ? (
                            <ReportEmpty message="No data yet." />
                        ) : (
                            <div className="space-y-3">
                                {importance.map((row) => (
                                    <RankingBar
                                        key={row.label}
                                        label={row.label}
                                        sublabel={`${row.completion}% complete · ${row.evaluations} scored`}
                                        value={row.average_score}
                                    />
                                ))}
                            </div>
                        )}
                    </ReportCard>
                </div>

                <ReportCard
                    title="Weakest categories"
                    description="Lowest-scoring categories across all stations"
                >
                    {categories.length === 0 ? (
                        <ReportEmpty message="No category data yet." />
                    ) : (
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Category</TableHead>
                                        <TableHead>Station</TableHead>
                                        <TableHead className="text-right">
                                            Avg score
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Completion
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {categories.map((category) => (
                                        <TableRow key={category.id}>
                                            <TableCell className="font-medium">
                                                {category.title}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {category.section_title}
                                            </TableCell>
                                            <TableCell className="text-right font-semibold tabular-nums">
                                                {category.average_score ?? '—'}
                                                {category.average_score !==
                                                    null && '%'}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {category.completion}%
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </ReportCard>

                <ReportCard
                    title="Problem items"
                    description="Lowest-scoring checklist items across all trainees"
                >
                    {problems.length === 0 ? (
                        <ReportEmpty message="No scored items yet." />
                    ) : (
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Item</TableHead>
                                        <TableHead>Station</TableHead>
                                        <TableHead className="text-right">
                                            Avg score
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Evals
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {problems.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell className="font-medium">
                                                {item.title}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {item.section_title} ·{' '}
                                                {item.category_title}
                                            </TableCell>
                                            <TableCell className="text-right font-semibold tabular-nums">
                                                {item.average_score}%
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {item.evaluations}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </ReportCard>
            </div>
        </Deferred>
    );
}
