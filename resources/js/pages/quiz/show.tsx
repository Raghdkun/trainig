import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    Check,
    CheckCircle2,
    CircleDot,
    ListChecks,
    ShieldAlert,
    UserCheck,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { reportMismatch, start, store } from '@/routes/quiz';

type QuestionType = 'single' | 'multi';
type Option = { id: number; text: string };
type Question = {
    id: number;
    prompt: string;
    type: QuestionType;
    options: Option[];
};

function PageShell({
    sectionTitle,
    subtitle,
    children,
}: {
    sectionTitle: string | null;
    subtitle: string;
    children: ReactNode;
}) {
    return (
        <div className="ambient-grid flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
            <Head
                title={sectionTitle ? `${sectionTitle} quiz` : 'Quick quiz'}
            />

            <div className="w-full max-w-lg">
                <div className="mb-6 flex flex-col items-center gap-3 text-center">
                    <div className="animate-rise flex size-11 items-center justify-center rounded-2xl bg-sidebar-primary text-sidebar-primary-foreground shadow-sm">
                        <AppLogoIcon className="size-6 fill-current" />
                    </div>
                    <div>
                        <h1 className="text-lg font-semibold tracking-tight">
                            {sectionTitle
                                ? `${sectionTitle}: Quick Quiz`
                                : 'Quick Quiz'}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {subtitle}
                        </p>
                    </div>
                </div>

                {children}
            </div>
        </div>
    );
}

export default function QuizShow() {
    const {
        traineeName,
        sectionTitle,
        completed,
        flaggedAsMismatch,
        questions,
        token,
    } = usePage<{
        traineeName: string;
        sectionTitle: string | null;
        completed: boolean;
        flaggedAsMismatch: boolean;
        questions: Question[];
        token: string;
    }>().props;

    const [identityConfirmed, setIdentityConfirmed] = useState(false);
    const [reporting, setReporting] = useState(false);
    const [starting, setStarting] = useState(false);

    const form = useForm<{ answers: Record<number, number[]> }>({
        answers: {},
    });

    function choose(questionId: number, optionId: number, type: QuestionType) {
        const current = form.data.answers[questionId] ?? [];

        if (type === 'single') {
            form.setData('answers', {
                ...form.data.answers,
                [questionId]: [optionId],
            });

            return;
        }

        const next = current.includes(optionId)
            ? current.filter((id) => id !== optionId)
            : [...current, optionId];

        form.setData('answers', {
            ...form.data.answers,
            [questionId]: next,
        });
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(store(token).url);
    }

    /**
     * Records that the trainee opened the quiz (so the training team sees it
     * as in progress), then shows the questions either way — a failed ping
     * shouldn't block someone from taking their quiz.
     */
    function confirmIdentity() {
        setStarting(true);
        router.post(
            start(token).url,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => {
                    setStarting(false);
                    setIdentityConfirmed(true);
                },
            },
        );
    }

    function notMe() {
        setReporting(true);
        router.post(
            reportMismatch(token).url,
            {},
            { onFinish: () => setReporting(false) },
        );
    }

    const answeredCount = questions.filter(
        (question) => (form.data.answers[question.id] ?? []).length > 0,
    ).length;
    const allAnswered =
        questions.length > 0 && answeredCount === questions.length;
    const progress =
        questions.length > 0
            ? Math.round((answeredCount / questions.length) * 100)
            : 0;
    const hasMultiChoice = questions.some(
        (question) => question.type === 'multi',
    );

    if (flaggedAsMismatch) {
        return (
            <PageShell
                sectionTitle={sectionTitle}
                subtitle="This link was reported as sent to the wrong person."
            >
                <div className="surface-tray animate-rise">
                    <div className="surface-core flex flex-col items-center gap-3 p-8 text-center">
                        <div className="flex size-14 items-center justify-center rounded-full bg-amber-500/10 text-amber-500 ring-1 ring-amber-500/20">
                            <ShieldAlert
                                className="size-7"
                                strokeWidth={1.75}
                            />
                        </div>
                        <h2 className="text-base font-semibold">
                            This quiz isn't for you
                        </h2>
                        <p className="max-w-xs text-sm text-muted-foreground">
                            Thanks for flagging it. Your manager has been noted
                            as sending this to the wrong person. You can close
                            this page now.
                        </p>
                    </div>
                </div>
            </PageShell>
        );
    }

    if (!completed && !identityConfirmed) {
        return (
            <PageShell
                sectionTitle={sectionTitle}
                subtitle="Before we start, let's make sure this link reached the right person."
            >
                <div className="surface-tray animate-rise">
                    <div className="surface-core flex flex-col items-center gap-4 p-8 text-center">
                        <div className="flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary ring-1 ring-primary/20">
                            <UserCheck className="size-7" strokeWidth={1.75} />
                        </div>
                        <div>
                            <h2 className="text-base font-semibold">
                                This quiz is for {traineeName}
                            </h2>
                            <p className="mt-1 max-w-xs text-sm text-muted-foreground">
                                Are you {traineeName}?
                            </p>
                        </div>
                        <div className="flex w-full flex-col gap-2 sm:flex-row">
                            <Button
                                variant="outline"
                                className="flex-1"
                                disabled={reporting || starting}
                                onClick={notMe}
                            >
                                No, this isn't me
                            </Button>
                            <Button
                                className="flex-1"
                                disabled={starting || reporting}
                                onClick={confirmIdentity}
                            >
                                Yes, that's me
                            </Button>
                        </div>
                    </div>
                </div>
            </PageShell>
        );
    }

    return (
        <PageShell
            sectionTitle={sectionTitle}
            subtitle={`Hi ${traineeName}, this should only take a minute.`}
        >
            {completed ? (
                <div className="surface-tray animate-rise">
                    <div className="surface-core flex flex-col items-center gap-3 p-8 text-center">
                        <div className="flex size-14 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-500 ring-1 ring-emerald-500/20">
                            <CheckCircle2
                                className="size-7"
                                strokeWidth={1.75}
                            />
                        </div>
                        <h2 className="text-base font-semibold">
                            Thanks, you're done
                        </h2>
                        <p className="max-w-xs text-sm text-muted-foreground">
                            Your answers have been sent to the training team.
                            You can close this page now.
                        </p>
                    </div>
                </div>
            ) : (
                <form onSubmit={submit} className="animate-rise space-y-4">
                    <div className="surface-tray sticky top-3 z-10 bg-background/80 backdrop-blur-sm">
                        <div className="surface-core overflow-hidden">
                            <div className="h-1 w-full bg-muted">
                                <div
                                    className="h-full bg-primary transition-[width] duration-300"
                                    style={{ width: `${progress}%` }}
                                />
                            </div>
                            <div className="flex items-center justify-between px-5 py-3 text-xs font-medium text-muted-foreground">
                                <span>
                                    {answeredCount} of {questions.length}{' '}
                                    answered
                                </span>
                                <span className="tabular-nums">
                                    {progress}%
                                </span>
                            </div>
                        </div>
                    </div>

                    {hasMultiChoice && (
                        <div className="flex items-start gap-2.5 rounded-xl border border-primary/20 bg-primary/5 p-3 text-sm">
                            <ListChecks className="mt-0.5 size-4 shrink-0 text-primary" />
                            <p className="text-muted-foreground">
                                Some questions have{' '}
                                <span className="font-medium text-foreground">
                                    more than one correct answer
                                </span>
                                . When you see{' '}
                                <span className="font-medium text-foreground">
                                    Select all that apply
                                </span>
                                , pick every answer that's right.
                            </p>
                        </div>
                    )}

                    {questions.map((question, index) => (
                        <QuestionCard
                            key={question.id}
                            question={question}
                            number={index + 1}
                            total={questions.length}
                            selectedIds={form.data.answers[question.id] ?? []}
                            onChoose={(optionId) =>
                                choose(question.id, optionId, question.type)
                            }
                        />
                    ))}

                    {form.hasErrors && (
                        <div
                            role="alert"
                            className="flex items-start gap-2.5 rounded-xl border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive"
                        >
                            <AlertCircle className="mt-0.5 size-4 shrink-0" />
                            <p>
                                Some answers couldn't be saved. Make sure every
                                question is answered, then submit again.
                            </p>
                        </div>
                    )}

                    <Button
                        type="submit"
                        size="lg"
                        className="w-full"
                        disabled={!allAnswered || form.processing}
                    >
                        {form.processing ? 'Submitting…' : 'Submit answers'}
                    </Button>
                    {!allAnswered && (
                        <p className="text-center text-xs text-muted-foreground">
                            Answer all {questions.length} questions to submit (
                            {answeredCount}/{questions.length} so far).
                        </p>
                    )}
                </form>
            )}
        </PageShell>
    );
}

/**
 * One question. Single-answer questions render as a radio group ("Choose
 * one") and multi-answer ones as a checkbox group ("Select all that apply"),
 * so the answer type is obvious before the trainee taps anything.
 */
function QuestionCard({
    question,
    number,
    total,
    selectedIds,
    onChoose,
}: {
    question: Question;
    number: number;
    total: number;
    selectedIds: number[];
    onChoose: (optionId: number) => void;
}) {
    const isMulti = question.type === 'multi';
    const answered = selectedIds.length > 0;
    const promptId = `question-${question.id}-prompt`;

    return (
        <div className="surface-tray">
            <div className="surface-core p-5">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <span
                        className={cn(
                            'flex items-center gap-1.5 text-xs font-medium transition-colors',
                            answered
                                ? 'text-emerald-600 dark:text-emerald-400'
                                : 'text-muted-foreground',
                        )}
                    >
                        {answered && <CheckCircle2 className="size-3.5" />}
                        Question {number} of {total}
                    </span>
                    <span
                        className={cn(
                            'inline-flex items-center gap-1.5 rounded-md border px-2 py-0.5 text-xs font-medium',
                            isMulti
                                ? 'border-primary/30 bg-primary/10 text-primary'
                                : 'border-border/60 text-muted-foreground',
                        )}
                    >
                        {isMulti ? (
                            <ListChecks className="size-3.5" />
                        ) : (
                            <CircleDot className="size-3.5" />
                        )}
                        {isMulti ? 'Select all that apply' : 'Choose one'}
                    </span>
                </div>

                <p
                    id={promptId}
                    className="mt-3 text-base leading-snug font-medium text-pretty"
                >
                    {question.prompt}
                </p>
                {isMulti && (
                    <p className="mt-1 text-xs text-muted-foreground">
                        More than one answer is correct ·{' '}
                        <span className="tabular-nums">
                            {selectedIds.length} selected
                        </span>
                    </p>
                )}

                <div
                    role={isMulti ? 'group' : 'radiogroup'}
                    aria-labelledby={promptId}
                    className="mt-4 grid gap-2"
                >
                    {question.options.map((option) => {
                        const selected = selectedIds.includes(option.id);

                        return (
                            <button
                                key={option.id}
                                type="button"
                                role={isMulti ? 'checkbox' : 'radio'}
                                aria-checked={selected}
                                onClick={() => onChoose(option.id)}
                                className={cn(
                                    'flex items-center gap-3 rounded-lg border p-3 text-left text-sm transition-[color,background-color,border-color,transform] duration-200 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:scale-[0.99]',
                                    selected
                                        ? 'border-primary bg-primary/10 font-medium'
                                        : 'border-border/60 hover:border-primary/30 hover:bg-muted/50',
                                )}
                            >
                                <span
                                    aria-hidden
                                    className={cn(
                                        'flex size-5 shrink-0 items-center justify-center border-2 transition-colors',
                                        isMulti
                                            ? 'rounded-[5px]'
                                            : 'rounded-full',
                                        selected
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-muted-foreground/40',
                                    )}
                                >
                                    {selected &&
                                        (isMulti ? (
                                            <Check
                                                className="size-3.5"
                                                strokeWidth={3}
                                            />
                                        ) : (
                                            <span className="size-2 rounded-full bg-primary-foreground" />
                                        ))}
                                </span>
                                <span className="min-w-0 flex-1">
                                    {option.text}
                                </span>
                            </button>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
