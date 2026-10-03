import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    CheckCircle2,
    CircleDot,
    Copy,
    Hourglass,
    ListChecks,
    RotateCcw,
    ShieldAlert,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { LocalTime } from '@/components/local-time';
import { ConfirmDeleteDialog } from '@/components/training/confirm-delete-dialog';
import { QuizStatusBadge } from '@/components/training/quiz-status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useClipboard } from '@/hooks/use-clipboard';
import { OPTION_LETTERS, PASSING_SCORE, isPassingScore } from '@/lib/quiz';
import { cn } from '@/lib/utils';
import { destroy, index, show } from '@/routes/training/quiz-results';
import type { BreadcrumbItem } from '@/types';
import type {
    QuizResultDetail,
    QuizResultOption,
    QuizResultQuestion,
} from '@/types/training';

type QuestionFilter = 'all' | 'incorrect';

export default function QuizResultShow() {
    const { attempt, questions } = usePage<{
        attempt: QuizResultDetail;
        questions: QuizResultQuestion[];
    }>().props;

    const [filter, setFilter] = useState<QuestionFilter>('all');
    const [copiedLink, copy] = useClipboard();

    const completed = attempt.status === 'completed';
    const incorrectCount = questions.filter((q) => !q.is_correct).length;
    const visibleQuestions = questions
        .map((question, position) => ({ question, number: position + 1 }))
        .filter(({ question }) => filter === 'all' || !question.is_correct);

    return (
        <>
            <Head
                title={`${attempt.trainee.name}: ${attempt.section.title} quiz`}
            />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <Link
                    href={index().url}
                    className="flex w-fit items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="size-4" /> Quiz Results
                </Link>

                <section className="surface-tray animate-rise">
                    <div className="surface-core flex flex-col gap-5 p-5">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div className="min-w-0 space-y-1.5">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h1 className="text-xl font-semibold tracking-tight">
                                        {attempt.trainee.name}
                                    </h1>
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
                                <p className="text-sm text-muted-foreground">
                                    {attempt.section.title} quiz · version{' '}
                                    {attempt.version} · {attempt.store.name}
                                </p>
                            </div>

                            <div className="flex items-center gap-3">
                                {completed && attempt.score !== null && (
                                    <ScoreSummary
                                        score={attempt.score}
                                        correct={attempt.correct_count ?? 0}
                                        total={attempt.questions_count}
                                    />
                                )}
                                <ConfirmDeleteDialog
                                    title="Reset this quiz?"
                                    description={`This deletes ${attempt.trainee.name}'s attempt${completed ? ' and answers' : ''}, and their current link stops working. You can then send the quiz again from their trainee page.`}
                                    confirmLabel="Reset quiz"
                                    onConfirm={(close) =>
                                        router.delete(destroy(attempt.id).url, {
                                            onSuccess: close,
                                        })
                                    }
                                    trigger={
                                        <Button variant="outline">
                                            <RotateCcw className="size-4" />{' '}
                                            Reset
                                        </Button>
                                    }
                                />
                            </div>
                        </div>

                        <Timeline attempt={attempt} />
                    </div>
                </section>

                {attempt.flagged && (
                    <div className="flex items-start gap-2.5 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-700 dark:text-amber-400">
                        <ShieldAlert className="mt-0.5 size-4 shrink-0" />
                        <p>
                            Someone who opened this link said it wasn't meant
                            for them. If {attempt.trainee.name} didn't receive
                            it, reset the quiz and send a new link.
                        </p>
                    </div>
                )}

                {completed ? (
                    <section className="space-y-3">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
                                <span className="flex items-center gap-1.5">
                                    <span className="size-2.5 rounded-full bg-emerald-500" />
                                    Correct answer
                                </span>
                                <span className="flex items-center gap-1.5">
                                    <span className="size-2.5 rounded-full bg-destructive" />
                                    Wrong pick
                                </span>
                                <span className="flex items-center gap-1.5">
                                    <span className="size-2.5 rounded-full border-2 border-dashed border-emerald-500" />
                                    Correct but missed
                                </span>
                            </div>
                            {incorrectCount > 0 && (
                                <ToggleGroup
                                    type="single"
                                    variant="outline"
                                    size="sm"
                                    value={filter}
                                    onValueChange={(value) =>
                                        value &&
                                        setFilter(value as QuestionFilter)
                                    }
                                >
                                    <ToggleGroupItem value="all">
                                        All ({questions.length})
                                    </ToggleGroupItem>
                                    <ToggleGroupItem value="incorrect">
                                        Incorrect ({incorrectCount})
                                    </ToggleGroupItem>
                                </ToggleGroup>
                            )}
                        </div>

                        {visibleQuestions.map(({ question, number }) => (
                            <QuestionResult
                                key={question.id}
                                question={question}
                                number={number}
                            />
                        ))}
                    </section>
                ) : (
                    <section className="surface-tray">
                        <div className="surface-core flex flex-col items-center gap-4 p-8 text-center">
                            <div className="flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                <Hourglass
                                    className="size-6"
                                    strokeWidth={1.75}
                                />
                            </div>
                            <div className="space-y-1">
                                <p className="font-medium">No answers yet</p>
                                <p className="max-w-sm text-sm text-balance text-muted-foreground">
                                    {attempt.status === 'in_progress'
                                        ? `${attempt.trainee.name} opened the quiz but hasn't submitted it yet.`
                                        : `${attempt.trainee.name} hasn't started the quiz yet.${attempt.flagged ? '' : ' Make sure they got the link.'}`}{' '}
                                    Their score and answers will appear here
                                    once they submit.
                                </p>
                            </div>
                            {attempt.link && (
                                <div className="flex w-full max-w-md gap-2">
                                    <Input
                                        readOnly
                                        value={attempt.link}
                                        aria-label="Quiz link"
                                        className="font-mono text-xs"
                                        onFocus={(event) =>
                                            event.currentTarget.select()
                                        }
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="shrink-0"
                                        onClick={() =>
                                            attempt.link && copy(attempt.link)
                                        }
                                    >
                                        {copiedLink === attempt.link ? (
                                            <>
                                                <Check className="size-4" />{' '}
                                                Copied
                                            </>
                                        ) : (
                                            <>
                                                <Copy className="size-4" /> Copy
                                                link
                                            </>
                                        )}
                                    </Button>
                                </div>
                            )}
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}

function ScoreSummary({
    score,
    correct,
    total,
}: {
    score: number;
    correct: number;
    total: number;
}) {
    const passed = isPassingScore(score);

    return (
        <div
            className={cn(
                'flex items-center gap-3 rounded-xl px-4 py-2 ring-1',
                passed
                    ? 'bg-emerald-500/10 ring-emerald-500/20'
                    : 'bg-destructive/5 ring-destructive/20',
            )}
        >
            <span
                className={cn(
                    'text-3xl leading-none font-semibold tabular-nums',
                    passed
                        ? 'text-emerald-600 dark:text-emerald-400'
                        : 'text-destructive',
                )}
            >
                {score}%
            </span>
            <span className="flex flex-col text-xs leading-tight">
                <span className="font-medium">
                    {passed ? 'Passed' : `Below ${PASSING_SCORE}%`}
                </span>
                <span className="text-muted-foreground tabular-nums">
                    {correct} of {total} correct
                </span>
            </span>
        </div>
    );
}

/** Link created → Opened → Submitted, with each step's timestamp. */
function Timeline({ attempt }: { attempt: QuizResultDetail }) {
    const steps = [
        { label: 'Link created', at: attempt.sent_at },
        { label: 'Opened', at: attempt.started_at },
        { label: 'Submitted', at: attempt.completed_at },
    ];

    return (
        <ol className="grid gap-3 border-t border-border/60 pt-4 sm:grid-cols-3">
            {steps.map((step) => (
                <li key={step.label} className="flex items-center gap-2.5">
                    <span
                        className={cn(
                            'flex size-6 shrink-0 items-center justify-center rounded-full',
                            step.at
                                ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                : 'border border-dashed border-border text-muted-foreground',
                        )}
                    >
                        {step.at && <Check className="size-3.5" />}
                    </span>
                    <span className="flex flex-col text-xs leading-tight">
                        <span
                            className={cn(
                                'font-medium',
                                !step.at && 'text-muted-foreground',
                            )}
                        >
                            {step.label}
                        </span>
                        <span className="text-muted-foreground">
                            {step.at ? (
                                <LocalTime iso={step.at} variant="datetime" />
                            ) : (
                                'Not yet'
                            )}
                        </span>
                    </span>
                </li>
            ))}
        </ol>
    );
}

function QuestionResult({
    question,
    number,
}: {
    question: QuizResultQuestion;
    number: number;
}) {
    const isMulti = question.type === 'multi';

    return (
        <div className="surface-tray">
            <div className="surface-core p-5">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-2 text-xs text-muted-foreground">
                        <span className="font-medium">Question {number}</span>
                        <span className="inline-flex items-center gap-1 rounded-md border border-border/60 px-1.5 py-0.5">
                            {isMulti ? (
                                <ListChecks className="size-3" />
                            ) : (
                                <CircleDot className="size-3" />
                            )}
                            {isMulti ? 'Select all that apply' : 'Choose one'}
                        </span>
                    </div>
                    <span
                        className={cn(
                            'inline-flex items-center gap-1 text-xs font-medium',
                            question.is_correct
                                ? 'text-emerald-600 dark:text-emerald-400'
                                : 'text-destructive',
                        )}
                    >
                        {question.is_correct ? (
                            <CheckCircle2 className="size-3.5" />
                        ) : (
                            <XCircle className="size-3.5" />
                        )}
                        {question.is_correct ? 'Correct' : 'Incorrect'}
                    </span>
                </div>

                <p className="mt-3 text-sm font-medium text-pretty">
                    {question.prompt}
                </p>

                <div className="mt-3 grid gap-2">
                    {question.options.map((option, optionIndex) => (
                        <OptionResult
                            key={option.id}
                            option={option}
                            letter={OPTION_LETTERS[optionIndex]}
                        />
                    ))}
                </div>
            </div>
        </div>
    );
}

function OptionResult({
    option,
    letter,
}: {
    option: QuizResultOption;
    letter: string;
}) {
    const missed = option.is_correct && !option.is_chosen;
    const wrongPick = option.is_chosen && !option.is_correct;

    return (
        <div
            className={cn(
                'flex items-center gap-3 rounded-lg border p-2.5 text-sm',
                option.is_correct &&
                    option.is_chosen &&
                    'border-emerald-500/50 bg-emerald-500/5',
                missed && 'border-dashed border-emerald-500/60',
                wrongPick && 'border-destructive/50 bg-destructive/5',
                !option.is_correct && !option.is_chosen && 'border-border/60',
            )}
        >
            <span
                className={cn(
                    'flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                    option.is_correct && option.is_chosen
                        ? 'bg-emerald-500 text-white'
                        : wrongPick
                          ? 'bg-destructive text-white'
                          : 'bg-muted text-muted-foreground',
                )}
            >
                {letter}
            </span>
            <span
                className={cn(
                    'min-w-0 flex-1',
                    option.is_chosen && 'font-medium',
                )}
            >
                {option.text}
            </span>
            {option.is_chosen && (
                <span className="shrink-0 text-xs text-muted-foreground">
                    Their answer
                </span>
            )}
            {missed && (
                <span className="shrink-0 text-xs text-emerald-600 dark:text-emerald-400">
                    Missed
                </span>
            )}
            {option.is_correct ? (
                <CheckCircle2 className="size-4 shrink-0 text-emerald-500" />
            ) : wrongPick ? (
                <XCircle className="size-4 shrink-0 text-destructive" />
            ) : null}
        </div>
    );
}

QuizResultShow.layout = (page: { attempt: QuizResultDetail }) => ({
    breadcrumbs: [
        { title: 'Quiz Results', href: index() },
        {
            title: `${page.attempt.trainee.name}: ${page.attempt.section.title}`,
            href: show(page.attempt.id),
        },
    ] satisfies BreadcrumbItem[],
});
