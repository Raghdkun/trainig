import { router } from '@inertiajs/react';
import {
    Check,
    CheckCircle2,
    Copy,
    FileQuestion,
    History,
    Send,
    ShieldAlert,
} from 'lucide-react';
import { useState } from 'react';
import { QuizStatusBadge } from '@/components/training/quiz-status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { store as sendQuiz } from '@/routes/trainees/quiz-attempts';
import type { SectionQuiz } from '@/types/training';

const MIN_QUESTIONS_TO_SEND = 3;

/**
 * A section's quiz status on the trainee page. Deliberately shows only
 * Not sent / Not started / In progress / Completed — never a score. Full results live only in the
 * training-team-only Quiz Results view. Sending and the shareable link are
 * only shown to viewers allowed to share quiz links (`canShare`).
 */
export function SectionQuizCard({
    traineeId,
    traineeName,
    quiz,
    readOnly,
    canShare,
}: {
    traineeId: number;
    traineeName: string;
    quiz: SectionQuiz;
    readOnly: boolean;
    canShare: boolean;
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

    const hasEnoughQuestions = quiz.questions_count >= MIN_QUESTIONS_TO_SEND;
    const canSend = !readOnly && canShare && hasEnoughQuestions;

    if (!quiz.attempt) {
        let description = 'Not sent yet';

        if (!hasEnoughQuestions) {
            description = `Needs at least ${MIN_QUESTIONS_TO_SEND} questions to send`;
        } else if (!canShare) {
            description =
                'Not sent yet. Only people allowed to share quiz links can send it.';
        }

        return (
            <Card className="flex flex-wrap items-center justify-between gap-3 p-3.5">
                <div className="flex items-center gap-3">
                    <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground">
                        <FileQuestion className="size-4" strokeWidth={1.75} />
                    </div>
                    <div>
                        <p className="text-sm font-medium">Quiz</p>
                        <p className="text-xs text-muted-foreground">
                            {description}
                        </p>
                    </div>
                </div>
                {canSend && (
                    <Button size="sm" onClick={send}>
                        <Send className="size-4" /> Send quiz
                    </Button>
                )}
            </Card>
        );
    }

    // The quiz was edited after this trainee's latest link was sent.
    const updatedNotice = quiz.is_outdated && (
        <div className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-primary/20 bg-primary/5 p-2.5 text-xs sm:ml-12">
            <span className="flex items-start gap-2 text-muted-foreground">
                <History className="size-4 shrink-0 text-primary" />
                <span>
                    This quiz was updated to{' '}
                    <span className="font-medium text-foreground">
                        v{quiz.version}
                    </span>{' '}
                    after this link (v{quiz.attempt.version}) was sent.{' '}
                    {quiz.attempt.status === 'completed'
                        ? `Their v${quiz.attempt.version} result is kept as it was.`
                        : `The v${quiz.attempt.version} link still works and still shows v${quiz.attempt.version}.`}
                </span>
            </span>
            {canSend && (
                <Button size="sm" variant="outline" onClick={send}>
                    <Send className="size-4" /> Send v{quiz.version}
                </Button>
            )}
        </div>
    );

    if (quiz.attempt.status === 'completed') {
        return (
            <Card className="flex flex-col gap-3 p-3.5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <CheckCircle2
                                className="size-4"
                                strokeWidth={1.75}
                            />
                        </div>
                        <div>
                            <p className="text-sm font-medium">Quiz</p>
                            <p className="text-xs text-muted-foreground">
                                Results go to the training team
                            </p>
                        </div>
                    </div>
                    <QuizStatusBadge status="completed" />
                </div>
                {updatedNotice}
            </Card>
        );
    }

    const link = quiz.attempt.link;
    const started = quiz.attempt.status === 'in_progress';

    return (
        <Card className="flex flex-col gap-3 p-3.5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <div
                        className={cn(
                            'flex size-9 shrink-0 items-center justify-center rounded-full',
                            started
                                ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                : 'bg-muted text-muted-foreground',
                        )}
                    >
                        <Send className="size-4" strokeWidth={1.75} />
                    </div>
                    <div>
                        <p className="text-sm font-medium">Quiz</p>
                        <p className="text-xs text-muted-foreground">
                            {started
                                ? 'Opened, waiting on the employee to submit'
                                : 'Link created, not opened yet'}
                        </p>
                    </div>
                </div>
                <QuizStatusBadge status={quiz.attempt.status} />
            </div>

            {updatedNotice}

            {quiz.attempt.flagged && (
                <div className="flex items-start gap-2 rounded-md border border-amber-500/30 bg-amber-500/10 p-2.5 text-xs text-amber-700 sm:ml-12 dark:text-amber-400">
                    <ShieldAlert className="size-4 shrink-0" />
                    <span>
                        Someone reported this link wasn't meant for them, so it
                        may have gone to the wrong person. Ask an admin to reset
                        it if needed.
                    </span>
                </div>
            )}

            {canShare && link && (
                <div className="space-y-1.5 sm:pl-12">
                    <p className="text-xs text-muted-foreground">
                        Version {quiz.attempt.version} link, only for{' '}
                        <span className="font-medium text-foreground">
                            {traineeName}
                        </span>
                        . Double-check before you share it.
                    </p>
                    <div className="flex gap-2">
                        <Input
                            readOnly
                            value={link}
                            className="font-mono text-xs"
                            onFocus={(event) => event.currentTarget.select()}
                        />
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="shrink-0"
                            onClick={() => copyLink(link)}
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
            )}
        </Card>
    );
}
