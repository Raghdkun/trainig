import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, RotateCcw, XCircle } from 'lucide-react';
import Heading from '@/components/heading';
import { ConfirmDeleteDialog } from '@/components/training/confirm-delete-dialog';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { destroy, index, show } from '@/routes/training/quiz-results';
import type { BreadcrumbItem } from '@/types';
import type { QuizResultDetail, QuizResultQuestion } from '@/types/training';

const OPTION_LETTERS = ['A', 'B', 'C', 'D'];

export default function QuizResultShow() {
    const { attempt, questions } = usePage<{
        attempt: QuizResultDetail;
        questions: QuizResultQuestion[];
    }>().props;

    const passed = attempt.score !== null && attempt.score >= 70;

    return (
        <>
            <Head
                title={`${attempt.trainee.name} — ${attempt.section.title} quiz`}
            />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <Link
                    href={index().url}
                    className="flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" /> Quiz Results
                </Link>

                <Card className="flex flex-wrap items-center justify-between gap-4 p-5">
                    <Heading
                        variant="small"
                        title={attempt.trainee.name}
                        description={`${attempt.section.title} quiz · Completed ${
                            attempt.completed_at
                                ? new Date(
                                      attempt.completed_at,
                                  ).toLocaleString()
                                : '—'
                        }`}
                    />
                    <div className="flex items-center gap-3">
                        <div
                            className={cn(
                                'flex flex-col items-end gap-0.5 rounded-xl px-4 py-2',
                                passed ? 'bg-emerald-500/10' : 'bg-muted',
                            )}
                        >
                            <span
                                className={cn(
                                    'text-2xl leading-none font-semibold tabular-nums',
                                    passed
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : 'text-foreground',
                                )}
                            >
                                {attempt.score}%
                            </span>
                            <span className="text-[11px] font-medium tracking-[0.1em] text-muted-foreground uppercase">
                                Score
                            </span>
                        </div>
                        <ConfirmDeleteDialog
                            title="Reset this quiz?"
                            description="This deletes the attempt and its answers, allowing the station to be sent to this trainee again."
                            onConfirm={(close) =>
                                router.delete(destroy(attempt.id).url, {
                                    onSuccess: close,
                                })
                            }
                            trigger={
                                <Button variant="outline">
                                    <RotateCcw className="size-4" /> Reset
                                </Button>
                            }
                        />
                    </div>
                </Card>

                <div className="space-y-3">
                    {questions.map((question, index) => (
                        <div key={question.id} className="surface-tray">
                            <div className="surface-core gap-3 p-5">
                                <p className="flex items-center gap-1.5 text-sm font-medium">
                                    <span className="text-muted-foreground">
                                        {index + 1}.
                                    </span>{' '}
                                    {question.prompt}
                                    {question.type === 'multi' && (
                                        <span className="shrink-0 rounded-full border border-border/60 px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground uppercase">
                                            Multi
                                        </span>
                                    )}
                                </p>
                                <div className="mt-3 grid gap-2">
                                    {question.options.map(
                                        (option, optionIndex) => (
                                            <div
                                                key={option.id}
                                                className={cn(
                                                    'flex items-center gap-3 rounded-lg border p-2.5 text-sm',
                                                    option.is_correct &&
                                                        'border-emerald-500/50 bg-emerald-500/5',
                                                    option.is_chosen &&
                                                        !option.is_correct &&
                                                        'border-destructive/50 bg-destructive/5',
                                                    !option.is_correct &&
                                                        !option.is_chosen &&
                                                        'border-border/60',
                                                )}
                                            >
                                                <span
                                                    className={cn(
                                                        'flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                                                        option.is_correct
                                                            ? 'bg-emerald-500 text-white'
                                                            : option.is_chosen
                                                              ? 'bg-destructive text-white'
                                                              : 'bg-muted text-muted-foreground',
                                                    )}
                                                >
                                                    {
                                                        OPTION_LETTERS[
                                                            optionIndex
                                                        ]
                                                    }
                                                </span>
                                                <span
                                                    className={cn(
                                                        'min-w-0 flex-1',
                                                        option.is_chosen &&
                                                            'font-medium',
                                                    )}
                                                >
                                                    {option.text}
                                                </span>
                                                {option.is_chosen && (
                                                    <span className="shrink-0 text-xs text-muted-foreground">
                                                        Chosen
                                                    </span>
                                                )}
                                                {option.is_correct ? (
                                                    <CheckCircle2 className="size-4 shrink-0 text-emerald-500" />
                                                ) : option.is_chosen ? (
                                                    <XCircle className="size-4 shrink-0 text-destructive" />
                                                ) : null}
                                            </div>
                                        ),
                                    )}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}

QuizResultShow.layout = (page: { attempt: QuizResultDetail }) => ({
    breadcrumbs: [
        { title: 'Quiz Results', href: index() },
        {
            title: `${page.attempt.trainee.name} — ${page.attempt.section.title}`,
            href: show(page.attempt.id),
        },
    ] satisfies BreadcrumbItem[],
});
