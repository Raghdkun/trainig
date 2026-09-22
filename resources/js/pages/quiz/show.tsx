import { Head, router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, ShieldAlert, UserCheck } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { reportMismatch, store } from '@/routes/quiz';

type QuestionType = 'single' | 'multi';
type Option = { id: number; text: string };
type Question = {
    id: number;
    prompt: string;
    type: QuestionType;
    options: Option[];
};

const OPTION_LETTERS = ['A', 'B', 'C', 'D'];

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
                                ? `${sectionTitle} — Quick Quiz`
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
                            Thanks for flagging it — your manager has been noted
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
                                disabled={reporting}
                                onClick={notMe}
                            >
                                No, this isn't me
                            </Button>
                            <Button
                                className="flex-1"
                                onClick={() => setIdentityConfirmed(true)}
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
                    <div className="surface-tray">
                        <div className="surface-core overflow-hidden">
                            <div className="h-1 w-full bg-muted">
                                <div
                                    className="h-full bg-primary transition-all duration-300"
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

                    {questions.map((question, index) => (
                        <div key={question.id} className="surface-tray">
                            <div className="surface-core gap-3 p-5">
                                <p className="text-sm font-medium">
                                    <span className="text-muted-foreground">
                                        {index + 1}.
                                    </span>{' '}
                                    {question.prompt}
                                </p>
                                {question.type === 'multi' && (
                                    <p className="text-xs text-muted-foreground">
                                        Select all that apply.
                                    </p>
                                )}
                                <div className="mt-3 grid gap-2">
                                    {question.options.map(
                                        (option, optionIndex) => {
                                            const selected = (
                                                form.data.answers[
                                                    question.id
                                                ] ?? []
                                            ).includes(option.id);

                                            return (
                                                <button
                                                    key={option.id}
                                                    type="button"
                                                    onClick={() =>
                                                        choose(
                                                            question.id,
                                                            option.id,
                                                            question.type,
                                                        )
                                                    }
                                                    aria-pressed={selected}
                                                    className={cn(
                                                        'flex items-center gap-3 rounded-lg border p-2.5 text-left text-sm transition-colors',
                                                        selected
                                                            ? 'border-primary bg-primary/10 font-medium'
                                                            : 'border-border/60 hover:border-primary/30 hover:bg-muted/50',
                                                    )}
                                                >
                                                    <span
                                                        className={cn(
                                                            'flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold transition-colors',
                                                            selected
                                                                ? 'bg-primary text-primary-foreground'
                                                                : 'bg-muted text-muted-foreground',
                                                        )}
                                                    >
                                                        {
                                                            OPTION_LETTERS[
                                                                optionIndex
                                                            ]
                                                        }
                                                    </span>
                                                    <span className="min-w-0 flex-1">
                                                        {option.text}
                                                    </span>
                                                    {selected && (
                                                        <CheckCircle2 className="size-4 shrink-0 text-primary" />
                                                    )}
                                                </button>
                                            );
                                        },
                                    )}
                                </div>
                            </div>
                        </div>
                    ))}

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
