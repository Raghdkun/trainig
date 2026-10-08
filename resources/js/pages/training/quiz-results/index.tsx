import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Check,
    ChevronRight,
    Copy,
    FileQuestion,
    Hourglass,
    Percent,
    Search,
    ShieldAlert,
    Store,
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
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useClipboard } from '@/hooks/use-clipboard';
import { useStoreFilter, useSyncStoreFilter } from '@/hooks/use-store-filter';
import { PASSING_SCORE, isPassingScore } from '@/lib/quiz';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/training/quiz-results';
import type { BreadcrumbItem } from '@/types';
import type {
    QuizAttemptRow,
    QuizAttemptStatus,
    StoreOption,
} from '@/types/training';

type StatusFilter = 'all' | QuizAttemptStatus | 'flagged';

const STATUS_TABS: { value: StatusFilter; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'not_started', label: 'Not started' },
    { value: 'in_progress', label: 'In progress' },
    { value: 'completed', label: 'Completed' },
    { value: 'flagged', label: 'Wrong person' },
];

type ResultFilter = 'all' | 'passed' | 'failed' | '90' | '70' | '50' | '0';

/**
 * Result/score filters. Only completed attempts have a score, so any choice
 * other than "All results" narrows the list to submitted quizzes.
 */
const RESULT_FILTERS: {
    value: Exclude<ResultFilter, 'all'>;
    label: string;
    matches: (score: number) => boolean;
}[] = [
    {
        value: 'passed',
        label: `Passed (${PASSING_SCORE}%+)`,
        matches: (score) => score >= PASSING_SCORE,
    },
    {
        value: 'failed',
        label: `Failed (under ${PASSING_SCORE}%)`,
        matches: (score) => score < PASSING_SCORE,
    },
    { value: '90', label: '90–100%', matches: (score) => score >= 90 },
    {
        value: '70',
        label: '70–89%',
        matches: (score) => score >= 70 && score < 90,
    },
    {
        value: '50',
        label: '50–69%',
        matches: (score) => score >= 50 && score < 70,
    },
    { value: '0', label: '0–49%', matches: (score) => score < 50 },
];

function matchesResult(attempt: QuizAttemptRow, filter: ResultFilter): boolean {
    if (filter === 'all') {
        return true;
    }

    if (attempt.status !== 'completed' || attempt.score === null) {
        return false;
    }

    const option = RESULT_FILTERS.find((f) => f.value === filter);

    return option ? option.matches(attempt.score) : true;
}

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
    const { attempts, stores, filters } = usePage<{
        attempts: QuizAttemptRow[];
        stores: StoreOption[];
        filters: { store: number | null };
    }>().props;

    const [status, setStatus] = useState<StatusFilter>('all');
    const [sectionId, setSectionId] = useState<string>('all');
    const [result, setResult] = useState<ResultFilter>('all');
    const [search, setSearch] = useState('');
    const [copiedLink, copy] = useClipboard();

    const { setSelectedStoreId } = useStoreFilter();
    useSyncStoreFilter(filters.store);

    function filterStore(value: string) {
        const storeId = value === 'all' ? null : Number(value);
        setSelectedStoreId(storeId);
        router.get(index().url, storeId ? { store: storeId } : {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    // The quiz and result filters define the data set the summary cards and
    // status counts describe; status and search only narrow the list.
    const scopedAttempts = useMemo(
        () =>
            attempts.filter(
                (attempt) =>
                    (sectionId === 'all' ||
                        String(attempt.section.id) === sectionId) &&
                    matchesResult(attempt, result),
            ),
        [attempts, sectionId, result],
    );

    const counts = useMemo(
        () => ({
            all: scopedAttempts.length,
            not_started: scopedAttempts.filter(
                (a) => a.status === 'not_started',
            ).length,
            in_progress: scopedAttempts.filter(
                (a) => a.status === 'in_progress',
            ).length,
            completed: scopedAttempts.filter((a) => a.status === 'completed')
                .length,
            flagged: scopedAttempts.filter((a) => a.flagged).length,
        }),
        [scopedAttempts],
    );

    const scores = scopedAttempts
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

        return scopedAttempts
            .filter((attempt) => {
                if (status === 'flagged') {
                    return attempt.flagged;
                }

                return status === 'all' || attempt.status === status;
            })
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
    }, [scopedAttempts, status, search]);

    const isFiltered =
        status !== 'all' ||
        sectionId !== 'all' ||
        result !== 'all' ||
        search !== '';

    function clearFilters() {
        setStatus('all');
        setSectionId('all');
        setResult('all');
        setSearch('');
    }

    return (
        <>
            <Head title="Quiz Results" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between [&_header]:mb-0">
                    <Heading
                        title="Quiz Results"
                        description="Every quiz link and result across the program. Only the training team can see scores and answers."
                    />
                    {stores.length > 1 && (
                        <Select
                            value={
                                filters.store ? String(filters.store) : 'all'
                            }
                            onValueChange={filterStore}
                        >
                            <SelectTrigger
                                className="w-full sm:w-48"
                                aria-label="Filter by store"
                            >
                                <span className="flex min-w-0 items-center gap-2">
                                    <Store className="size-4 shrink-0 text-muted-foreground" />
                                    <SelectValue placeholder="All stores" />
                                </span>
                            </SelectTrigger>
                            <SelectContent align="end">
                                <SelectItem value="all">All stores</SelectItem>
                                {stores.map((store) => (
                                    <SelectItem
                                        key={store.id}
                                        value={String(store.id)}
                                    >
                                        {store.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                </div>

                {attempts.length === 0 && filters.store === null ? (
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
                                <header className="border-b border-border/60">
                                    <div
                                        role="tablist"
                                        aria-label="Filter by status"
                                        className="flex [scrollbar-width:none] gap-1 overflow-x-auto border-b border-border/60 px-2 sm:px-3"
                                    >
                                        {STATUS_TABS.filter(
                                            (tab) =>
                                                tab.value !== 'flagged' ||
                                                counts.flagged > 0,
                                        ).map((tab) => (
                                            <StatusTab
                                                key={tab.value}
                                                label={tab.label}
                                                count={counts[tab.value]}
                                                active={status === tab.value}
                                                warning={
                                                    tab.value === 'flagged'
                                                }
                                                onSelect={() =>
                                                    setStatus(tab.value)
                                                }
                                            />
                                        ))}
                                    </div>

                                    <div className="grid gap-2 p-3 sm:grid-cols-2 sm:p-4 xl:grid-cols-[minmax(0,1fr)_13rem_13rem_auto]">
                                        <div className="relative sm:col-span-2 xl:col-span-1">
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
                                        <Select
                                            value={sectionId}
                                            onValueChange={setSectionId}
                                        >
                                            <SelectTrigger
                                                className="w-full"
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
                                        <Select
                                            value={result}
                                            onValueChange={(value) =>
                                                setResult(value as ResultFilter)
                                            }
                                        >
                                            <SelectTrigger
                                                className="w-full"
                                                aria-label="Filter by result"
                                            >
                                                <SelectValue placeholder="All results" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">
                                                    All results
                                                </SelectItem>
                                                {RESULT_FILTERS.map(
                                                    (option) => (
                                                        <SelectItem
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                        <Button
                                            variant="ghost"
                                            onClick={clearFilters}
                                            disabled={!isFiltered}
                                            className="hidden text-muted-foreground xl:inline-flex"
                                        >
                                            <X className="size-4" /> Clear
                                        </Button>
                                    </div>
                                </header>

                                {visibleAttempts.length === 0 ? (
                                    <div className="flex flex-col items-center gap-3 p-12 text-center">
                                        <Search className="size-8 text-muted-foreground" />
                                        <p className="text-sm text-muted-foreground">
                                            {attempts.length === 0
                                                ? 'No quiz links for this store yet.'
                                                : 'No quiz links match these filters.'}
                                        </p>
                                        {isFiltered && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={clearFilters}
                                            >
                                                <X className="size-4" /> Clear
                                                filters
                                            </Button>
                                        )}
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

/** One underlined status tab with its live count. */
function StatusTab({
    label,
    count,
    active,
    warning,
    onSelect,
}: {
    label: string;
    count: number;
    active: boolean;
    warning: boolean;
    onSelect: () => void;
}) {
    return (
        <button
            type="button"
            role="tab"
            aria-selected={active}
            onClick={onSelect}
            className={cn(
                '-mb-px flex shrink-0 items-center gap-2 border-b-2 px-3 py-3 text-sm font-medium whitespace-nowrap transition-colors',
                active
                    ? 'border-primary text-foreground'
                    : 'border-transparent text-muted-foreground hover:text-foreground',
                warning && 'text-amber-600 dark:text-amber-400',
            )}
        >
            {label}
            <span
                className={cn(
                    'min-w-5 rounded-full px-1.5 text-center text-[11px] leading-5 tabular-nums',
                    active
                        ? 'bg-primary/15 text-foreground'
                        : 'bg-muted text-muted-foreground',
                )}
            >
                {count}
            </span>
        </button>
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
