import { Head, Link, usePage } from '@inertiajs/react';
import {
    Check,
    ChevronRight,
    Copy,
    FileQuestion,
    Hourglass,
    Percent,
    Search,
    ShieldAlert,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { StatCard } from '@/components/dashboard/stat-card';
import Heading from '@/components/heading';
import { LocalTime } from '@/components/local-time';
import { QuizStatusBadge } from '@/components/training/quiz-status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useClipboard } from '@/hooks/use-clipboard';
import { PASSING_SCORE, isPassingScore } from '@/lib/quiz';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/training/quiz-results';
import type { BreadcrumbItem } from '@/types';
import type { QuizAttemptRow, QuizAttemptStatus } from '@/types/training';

type StatusFilter = 'all' | QuizAttemptStatus | 'flagged';

/** The attempt's most recent event, used for sorting and the Activity column. */
function latestActivity(attempt: QuizAttemptRow): {
    label: string;
    at: string;
} {
    if (attempt.completed_at) {
        return { label: 'Submitted', at: attempt.completed_at };
    }

    if (attempt.started_at) {
        return { label: 'Opened', at: attempt.started_at };
    }

    return { label: 'Link created', at: attempt.sent_at };
}

export default function QuizResultsIndex() {
    const { attempts } = usePage<{ attempts: QuizAttemptRow[] }>().props;

    const [status, setStatus] = useState<StatusFilter>('all');
    const [sectionId, setSectionId] = useState<string>('all');
    const [search, setSearch] = useState('');
    const [copiedLink, copy] = useClipboard();

    const counts = useMemo(
        () => ({
            all: attempts.length,
            not_started: attempts.filter((a) => a.status === 'not_started')
                .length,
            in_progress: attempts.filter((a) => a.status === 'in_progress')
                .length,
            completed: attempts.filter((a) => a.status === 'completed').length,
            flagged: attempts.filter((a) => a.flagged).length,
        }),
        [attempts],
    );

    const scores = attempts
        .filter((a) => a.status === 'completed' && a.score !== null)
        .map((a) => a.score as number);
    const averageScore = scores.length
        ? Math.round(scores.reduce((sum, s) => sum + s, 0) / scores.length)
        : null;
    const passedCount = scores.filter((s) => s >= PASSING_SCORE).length;

    const sections = useMemo(
        () =>
            [
                ...new Map(
                    attempts.map((a) => [a.section.id, a.section]),
                ).values(),
            ].sort((a, b) => a.title.localeCompare(b.title)),
        [attempts],
    );

    const visibleAttempts = useMemo(() => {
        const term = search.trim().toLowerCase();

        return attempts
            .filter((attempt) => {
                if (status === 'flagged') {
                    return attempt.flagged;
                }

                return status === 'all' || attempt.status === status;
            })
            .filter(
                (attempt) =>
                    sectionId === 'all' ||
                    String(attempt.section.id) === sectionId,
            )
            .filter(
                (attempt) =>
                    term === '' ||
                    attempt.trainee.name.toLowerCase().includes(term) ||
                    attempt.store.name.toLowerCase().includes(term),
            )
            .sort(
                (a, b) =>
                    new Date(latestActivity(b).at).getTime() -
                    new Date(latestActivity(a).at).getTime(),
            );
    }, [attempts, status, sectionId, search]);

    const isFiltered = status !== 'all' || sectionId !== 'all' || search !== '';

    function clearFilters() {
        setStatus('all');
        setSectionId('all');
        setSearch('');
    }

    return (
        <>
            <Head title="Quiz Results" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="Quiz Results"
                    description="Every quiz link and result across the program. Only the training team can see scores and answers."
                />

                {attempts.length === 0 ? (
                    <Card className="flex flex-col items-center justify-center gap-3 border-dashed p-12 text-center">
                        <div className="flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                            <FileQuestion className="size-6" />
                        </div>
                        <div className="space-y-1">
                            <p className="font-medium">No quiz links yet</p>
                            <p className="max-w-sm text-sm text-balance text-muted-foreground">
                                Open a trainee and use Send quiz on one of their
                                sections. Their link and results will show up
                                here.
                            </p>
                        </div>
                    </Card>
                ) : (
                    <>
                        <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                            <StatCard
                                label="Links created"
                                value={counts.all}
                                icon={FileQuestion}
                                hint={`Across ${sections.length} ${sections.length === 1 ? 'quiz' : 'quizzes'}`}
                            />
                            <StatCard
                                label="Awaiting"
                                value={counts.not_started + counts.in_progress}
                                icon={Hourglass}
                                hint={`${counts.not_started} not opened · ${counts.in_progress} in progress`}
                            />
                            <StatCard
                                label="Average score"
                                value={
                                    averageScore !== null
                                        ? `${averageScore}%`
                                        : '—'
                                }
                                icon={Percent}
                                hint={
                                    scores.length > 0
                                        ? `${passedCount} of ${scores.length} passed (${PASSING_SCORE}%+)`
                                        : 'No completed quizzes yet'
                                }
                            />
                            <StatCard
                                label="Wrong person"
                                value={counts.flagged}
                                icon={ShieldAlert}
                                hint={
                                    counts.flagged > 0
                                        ? 'Opened by someone it wasn’t meant for'
                                        : 'No reports'
                                }
                            />
                        </div>

                        <section className="surface-tray">
                            <div className="surface-core overflow-hidden">
                                <header className="flex flex-col gap-3 border-b border-border/60 p-4 lg:flex-row lg:items-center lg:justify-between">
                                    <ToggleGroup
                                        type="single"
                                        variant="outline"
                                        size="sm"
                                        value={status}
                                        onValueChange={(value) =>
                                            value &&
                                            setStatus(value as StatusFilter)
                                        }
                                        className="w-full justify-start overflow-x-auto lg:w-auto"
                                    >
                                        <ToggleGroupItem value="all">
                                            All
                                            <FilterCount value={counts.all} />
                                        </ToggleGroupItem>
                                        <ToggleGroupItem value="not_started">
                                            Not started
                                            <FilterCount
                                                value={counts.not_started}
                                            />
                                        </ToggleGroupItem>
                                        <ToggleGroupItem value="in_progress">
                                            In progress
                                            <FilterCount
                                                value={counts.in_progress}
                                            />
                                        </ToggleGroupItem>
                                        <ToggleGroupItem value="completed">
                                            Completed
                                            <FilterCount
                                                value={counts.completed}
                                            />
                                        </ToggleGroupItem>
                                        {counts.flagged > 0 && (
                                            <ToggleGroupItem
                                                value="flagged"
                                                className="text-amber-600 dark:text-amber-400"
                                            >
                                                Wrong person
                                                <FilterCount
                                                    value={counts.flagged}
                                                />
                                            </ToggleGroupItem>
                                        )}
                                    </ToggleGroup>

                                    <div className="flex flex-col gap-2 sm:flex-row">
                                        <div className="relative sm:w-64">
                                            <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                                            <Input
                                                value={search}
                                                onChange={(event) =>
                                                    setSearch(
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="Search trainee or store"
                                                aria-label="Search trainee or store"
                                                className="pl-8"
                                            />
                                        </div>
                                        {sections.length > 1 && (
                                            <Select
                                                value={sectionId}
                                                onValueChange={setSectionId}
                                            >
                                                <SelectTrigger
                                                    className="sm:w-48"
                                                    aria-label="Filter by quiz"
                                                >
                                                    <SelectValue placeholder="All quizzes" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="all">
                                                        All quizzes
                                                    </SelectItem>
                                                    {sections.map((section) => (
                                                        <SelectItem
                                                            key={section.id}
                                                            value={String(
                                                                section.id,
                                                            )}
                                                        >
                                                            {section.title}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        )}
                                    </div>
                                </header>

                                {visibleAttempts.length === 0 ? (
                                    <div className="flex flex-col items-center gap-3 p-12 text-center">
                                        <Search className="size-8 text-muted-foreground" />
                                        <p className="text-sm text-muted-foreground">
                                            No quiz links match these filters.
                                        </p>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={clearFilters}
                                        >
                                            <X className="size-4" /> Clear
                                            filters
                                        </Button>
                                    </div>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead className="pl-4">
                                                        Trainee
                                                    </TableHead>
                                                    <TableHead>Quiz</TableHead>
                                                    <TableHead>
                                                        Status
                                                    </TableHead>
                                                    <TableHead className="text-right">
                                                        Score
                                                    </TableHead>
                                                    <TableHead>
                                                        Last activity
                                                    </TableHead>
                                                    <TableHead className="pr-4 text-right">
                                                        <span className="sr-only">
                                                            Actions
                                                        </span>
                                                    </TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {visibleAttempts.map(
                                                    (attempt) => (
                                                        <AttemptRow
                                                            key={attempt.id}
                                                            attempt={attempt}
                                                            copied={
                                                                attempt.link !==
                                                                    null &&
                                                                copiedLink ===
                                                                    attempt.link
                                                            }
                                                            onCopy={copy}
                                                        />
                                                    ),
                                                )}
                                            </TableBody>
                                        </Table>
                                    </div>
                                )}

                                {isFiltered && visibleAttempts.length > 0 && (
                                    <footer className="flex items-center justify-between gap-3 border-t border-border/60 px-4 py-2.5 text-xs text-muted-foreground">
                                        <span>
                                            Showing {visibleAttempts.length} of{' '}
                                            {attempts.length}
                                        </span>
                                        <button
                                            type="button"
                                            onClick={clearFilters}
                                            className="font-medium text-foreground hover:underline"
                                        >
                                            Clear filters
                                        </button>
                                    </footer>
                                )}
                            </div>
                        </section>
                    </>
                )}
            </div>
        </>
    );
}

function FilterCount({ value }: { value: number }) {
    return (
        <span className="ml-1 rounded bg-muted px-1.5 text-[11px] font-medium text-muted-foreground tabular-nums">
            {value}
        </span>
    );
}

function AttemptRow({
    attempt,
    copied,
    onCopy,
}: {
    attempt: QuizAttemptRow;
    copied: boolean;
    onCopy: (text: string) => Promise<boolean>;
}) {
    const activity = latestActivity(attempt);
    const detailsUrl = show(attempt.id).url;

    return (
        <TableRow className="group">
            <TableCell className="pl-4">
                <Link
                    href={detailsUrl}
                    className="font-medium underline-offset-4 group-hover:underline"
                >
                    {attempt.trainee.name}
                </Link>
                <p className="text-xs text-muted-foreground">
                    {attempt.store.name}
                </p>
            </TableCell>
            <TableCell>
                <span className="flex items-center gap-1.5">
                    {attempt.section.title}
                    <span
                        className="rounded border border-border/60 px-1 text-[11px] text-muted-foreground tabular-nums"
                        title={`Sent as version ${attempt.version} of this quiz`}
                    >
                        v{attempt.version}
                    </span>
                </span>
            </TableCell>
            <TableCell>
                <div className="flex flex-wrap items-center gap-1.5">
                    <QuizStatusBadge status={attempt.status} />
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
            <TableCell className="text-right">
                {attempt.status === 'completed' && attempt.score !== null ? (
                    <ScoreMeter score={attempt.score} />
                ) : (
                    <span className="text-muted-foreground">—</span>
                )}
            </TableCell>
            <TableCell>
                <Tooltip>
                    <TooltipTrigger asChild>
                        <span className="text-sm whitespace-nowrap">
                            <span className="text-muted-foreground">
                                {activity.label}
                            </span>{' '}
                            <LocalTime iso={activity.at} variant="relative" />
                        </span>
                    </TooltipTrigger>
                    <TooltipContent>
                        <LocalTime iso={activity.at} variant="datetime" />
                    </TooltipContent>
                </Tooltip>
            </TableCell>
            <TableCell className="pr-4">
                <div className="flex items-center justify-end gap-1">
                    {attempt.link && (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-8"
                                    aria-label={`Copy quiz link for ${attempt.trainee.name}`}
                                    onClick={() =>
                                        attempt.link && onCopy(attempt.link)
                                    }
                                >
                                    {copied ? (
                                        <Check className="size-4 text-emerald-600 dark:text-emerald-400" />
                                    ) : (
                                        <Copy className="size-4" />
                                    )}
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                {copied ? 'Copied' : 'Copy quiz link'}
                            </TooltipContent>
                        </Tooltip>
                    )}
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-8 gap-1 text-muted-foreground hover:text-foreground"
                        asChild
                    >
                        <Link href={detailsUrl}>
                            {attempt.status === 'completed'
                                ? 'Answers'
                                : 'Details'}
                            <ChevronRight className="size-4" />
                        </Link>
                    </Button>
                </div>
            </TableCell>
        </TableRow>
    );
}

function ScoreMeter({ score }: { score: number }) {
    const passed = isPassingScore(score);

    return (
        <div className="flex items-center justify-end gap-2.5">
            <div
                aria-hidden
                className="hidden h-1.5 w-16 overflow-hidden rounded-full bg-muted sm:block"
            >
                <div
                    className={cn(
                        'h-full rounded-full',
                        passed ? 'bg-emerald-500' : 'bg-destructive/70',
                    )}
                    style={{ width: `${score}%` }}
                />
            </div>
            <span
                className={cn(
                    'w-11 text-right font-semibold tabular-nums',
                    passed
                        ? 'text-emerald-600 dark:text-emerald-400'
                        : 'text-destructive',
                )}
            >
                {score}%
            </span>
        </div>
    );
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Quiz Results', href: index() },
];

QuizResultsIndex.layout = { breadcrumbs };
