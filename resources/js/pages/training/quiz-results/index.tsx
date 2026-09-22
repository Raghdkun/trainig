import { Head, Link, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    FileQuestion,
    Percent,
    Send,
    ShieldAlert,
} from 'lucide-react';
import { StatCard } from '@/components/dashboard/stat-card';
import Heading from '@/components/heading';
import { QuizStatusBadge } from '@/components/training/quiz-status-badge';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/training/quiz-results';
import type { BreadcrumbItem } from '@/types';
import type { QuizAttemptRow } from '@/types/training';

export default function QuizResultsIndex() {
    const { attempts } = usePage<{ attempts: QuizAttemptRow[] }>().props;

    const completedAttempts = attempts.filter(
        (attempt) => attempt.status === 'completed' && attempt.score !== null,
    );
    const averageScore = completedAttempts.length
        ? Math.round(
              completedAttempts.reduce(
                  (sum, attempt) => sum + (attempt.score ?? 0),
                  0,
              ) / completedAttempts.length,
          )
        : null;
    const flaggedAttempts = attempts.filter((attempt) => attempt.flagged);

    return (
        <>
            <Head title="Quiz Results" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="Quiz Results"
                    description="Every quiz sent and completed across the program. Kept out of the Manager's view — only the training team sees answers and scores."
                />

                {attempts.length === 0 ? (
                    <Card className="flex flex-col items-center justify-center gap-3 border-dashed p-12 text-center">
                        <FileQuestion className="size-10 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            No quizzes have been sent yet.
                        </p>
                    </Card>
                ) : (
                    <>
                        <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                            <StatCard
                                label="Total attempts"
                                value={attempts.length}
                                icon={FileQuestion}
                            />
                            <StatCard
                                label="Awaiting completion"
                                value={
                                    attempts.length - completedAttempts.length
                                }
                                icon={Send}
                            />
                            <StatCard
                                label="Average score"
                                value={
                                    averageScore !== null
                                        ? `${averageScore}%`
                                        : '—'
                                }
                                icon={Percent}
                            />
                            <StatCard
                                label="Flagged: wrong person"
                                value={flaggedAttempts.length}
                                icon={ShieldAlert}
                                hint={
                                    flaggedAttempts.length > 0
                                        ? 'Reported by the person who opened the link'
                                        : undefined
                                }
                            />
                        </div>

                        <section className="surface-tray">
                            <div className="surface-core overflow-hidden">
                                <header className="flex items-center justify-between gap-3 border-b border-border/60 p-4">
                                    <div className="flex items-center gap-2">
                                        <CheckCircle2 className="size-4 text-muted-foreground" />
                                        <h2 className="font-semibold tracking-tight">
                                            All results
                                        </h2>
                                    </div>
                                    <span className="text-xs text-muted-foreground">
                                        {attempts.length}{' '}
                                        {attempts.length === 1
                                            ? 'attempt'
                                            : 'attempts'}
                                    </span>
                                </header>
                                <div className="overflow-x-auto">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Trainee</TableHead>
                                                <TableHead>Quiz</TableHead>
                                                <TableHead>Status</TableHead>
                                                <TableHead className="text-right">
                                                    Score
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    Sent
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {attempts.map((attempt) => (
                                                <TableRow key={attempt.id}>
                                                    <TableCell>
                                                        <p className="font-medium">
                                                            {
                                                                attempt.trainee
                                                                    .name
                                                            }
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {attempt.store.name}
                                                        </p>
                                                    </TableCell>
                                                    <TableCell>
                                                        {attempt.section.title}
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex flex-wrap items-center gap-1.5">
                                                            <QuizStatusBadge
                                                                status={
                                                                    attempt.status
                                                                }
                                                            />
                                                            {attempt.flagged && (
                                                                <Badge
                                                                    variant="outline"
                                                                    className="gap-1 text-amber-600 dark:text-amber-400"
                                                                >
                                                                    <ShieldAlert className="size-3" />
                                                                    Wrong person
                                                                </Badge>
                                                            )}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {attempt.status ===
                                                            'completed' &&
                                                        attempt.score !==
                                                            null ? (
                                                            <Link
                                                                href={
                                                                    show(
                                                                        attempt.id,
                                                                    ).url
                                                                }
                                                                className={cn(
                                                                    'font-semibold hover:underline',
                                                                    attempt.score >=
                                                                        70
                                                                        ? 'text-emerald-600 dark:text-emerald-400'
                                                                        : 'text-primary',
                                                                )}
                                                            >
                                                                {attempt.score}%
                                                            </Link>
                                                        ) : (
                                                            <span className="text-muted-foreground">
                                                                —
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right text-sm text-muted-foreground">
                                                        {new Date(
                                                            attempt.sent_at,
                                                        ).toLocaleDateString()}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            </div>
                        </section>
                    </>
                )}
            </div>
        </>
    );
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Quiz Results', href: index() },
];

QuizResultsIndex.layout = { breadcrumbs };
