import { router } from '@inertiajs/react';
import {
    Check,
    CheckCircle2,
    Copy,
    FileQuestion,
    Send,
    ShieldAlert,
} from 'lucide-react';
import { useState } from 'react';
import { QuizStatusBadge } from '@/components/training/quiz-status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { store as sendQuiz } from '@/routes/trainees/quiz-attempts';
import type { SectionQuiz } from '@/types/training';

const MIN_QUESTIONS_TO_SEND = 3;

/**
 * A section's quiz status on the trainee page. Deliberately shows only
 * Not sent / Sent (+ link) / Completed — never a score. Full results live
 * only in the training-team-only Quiz Results view.
 */
export function SectionQuizCard({
    traineeId,
    traineeName,
    quiz,
    readOnly,
}: {
    traineeId: number;
    traineeName: string;
    quiz: SectionQuiz;
    readOnly: boolean;
}) {
    const [copied, setCopied] = useState(false);

    function send() {
        router.post(
            sendQuiz(traineeId).url,
            { quiz_id: quiz.id },
            { preserveScroll: true },
        );
    }

    async function copyLink(link: string) {
        try {
            await navigator.clipboard.writeText(link);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // Clipboard access can fail (permissions, insecure context) —
            // the link is still selectable/copyable from the input itself.
        }
    }

    if (!quiz.attempt) {
        const canSend = quiz.questions_count >= MIN_QUESTIONS_TO_SEND;

        return (
            <Card className="flex flex-wrap items-center justify-between gap-3 p-3.5">
                <div className="flex items-center gap-3">
                    <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground">
                        <FileQuestion className="size-4" strokeWidth={1.75} />
                    </div>
                    <div>
                        <p className="text-sm font-medium">Quiz</p>
                        <p className="text-xs text-muted-foreground">
                            {canSend
                                ? 'Not sent yet'
                                : `Needs at least ${MIN_QUESTIONS_TO_SEND} questions to send`}
                        </p>
                    </div>
                </div>
                {!readOnly && canSend && (
                    <Button size="sm" onClick={send}>
                        <Send className="size-4" /> Send quiz
                    </Button>
                )}
            </Card>
        );
    }

    if (quiz.attempt.status === 'completed') {
        return (
            <Card className="flex flex-wrap items-center justify-between gap-3 p-3.5">
                <div className="flex items-center gap-3">
                    <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                        <CheckCircle2 className="size-4" strokeWidth={1.75} />
                    </div>
                    <div>
                        <p className="text-sm font-medium">Quiz</p>
                        <p className="text-xs text-muted-foreground">
                            Results go to the training team
                        </p>
                    </div>
                </div>
                <QuizStatusBadge status="completed" />
            </Card>
        );
    }

    return (
        <Card className="flex flex-col gap-3 p-3.5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400">
                        <Send className="size-4" strokeWidth={1.75} />
                    </div>
                    <div>
                        <p className="text-sm font-medium">Quiz</p>
                        <p className="text-xs text-muted-foreground">
                            Waiting on the employee to finish
                        </p>
                    </div>
                </div>
                <QuizStatusBadge status="sent" />
            </div>

            {quiz.attempt.flagged && (
                <div className="flex items-start gap-2 rounded-md border border-amber-500/30 bg-amber-500/10 p-2.5 text-xs text-amber-700 sm:ml-12 dark:text-amber-400">
                    <ShieldAlert className="size-4 shrink-0" />
                    <span>
                        Someone reported this link wasn't meant for them — this
                        may have gone to the wrong person. Ask an admin to reset
                        it if needed.
                    </span>
                </div>
            )}

            <div className="space-y-1.5 sm:pl-12">
                <p className="text-xs text-muted-foreground">
                    This link is only for{' '}
                    <span className="font-medium text-foreground">
                        {traineeName}
                    </span>{' '}
                    — double-check before you share it.
                </p>
                <div className="flex gap-2">
                    <Input
                        readOnly
                        value={quiz.attempt.link ?? ''}
                        className="font-mono text-xs"
                        onFocus={(event) => event.currentTarget.select()}
                    />
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="shrink-0"
                        onClick={() =>
                            quiz.attempt?.link && copyLink(quiz.attempt.link)
                        }
                    >
                        {copied ? (
                            <>
                                <Check className="size-4" /> Copied
                            </>
                        ) : (
                            <>
                                <Copy className="size-4" /> Copy
                            </>
                        )}
                    </Button>
                </div>
            </div>
        </Card>
    );
}
