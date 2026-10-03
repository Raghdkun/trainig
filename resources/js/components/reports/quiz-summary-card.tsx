import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { ReportCard, ReportEmpty } from '@/components/reports/report-card';
import { Button } from '@/components/ui/button';
import { index as quizResultsIndex } from '@/routes/training/quiz-results';
import type { QuizResultsSummary } from '@/types/reports';

export function QuizSummaryCard({ summary }: { summary?: QuizResultsSummary }) {
    return (
        <ReportCard
            title="Quiz results"
            description="Station quizzes sent and completed"
            action={
                <Button variant="outline" size="sm" asChild>
                    <Link href={quizResultsIndex().url}>
                        View all <ArrowRight className="size-4" />
                    </Link>
                </Button>
            }
        >
            {!summary || summary.sent === 0 ? (
                <ReportEmpty message="No quizzes have been sent yet." />
            ) : (
                <div className="grid grid-cols-3 gap-3">
                    <div className="rounded-lg border p-3 text-center">
                        <p className="text-2xl font-semibold tabular-nums">
                            {summary.sent}
                        </p>
                        <p className="text-xs text-muted-foreground">Sent</p>
                    </div>
                    <div className="rounded-lg border p-3 text-center">
                        <p className="text-2xl font-semibold tabular-nums">
                            {summary.completed}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Completed
                        </p>
                    </div>
                    <div className="rounded-lg border p-3 text-center">
                        <p className="text-2xl font-semibold tabular-nums">
                            {summary.average_score !== null
                                ? `${summary.average_score}%`
                                : '—'}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Avg score
                        </p>
                    </div>
                </div>
            )}
        </ReportCard>
    );
}
