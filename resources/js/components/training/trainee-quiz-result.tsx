import {
    CheckCircle2,
    LockKeyhole,
    PartyPopper,
    RotateCcw,
} from 'lucide-react';
import { useState } from 'react';
import { QuizQuestionResult } from '@/components/training/quiz-question-result';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { PASSING_SCORE, isPassingScore } from '@/lib/quiz';
import { cn } from '@/lib/utils';
import type { TraineeQuizResult } from '@/types/training';

/**
 * What the trainee sees right after submitting: their score, then the
 * questions they got wrong with the correct answer and the explanation, so
 * they know exactly what to review. Every question is one tap away.
 */
export function TraineeQuizResultView({
    result,
    onFinish,
    finishing,
}: {
    result: TraineeQuizResult;
    /** Confirms the results were reviewed and closes the link for good. */
    onFinish: () => void;
    finishing: boolean;
}) {
    const passed = isPassingScore(result.score);
    const numbered = result.questions.map((question, index) => ({
        question,
        number: index + 1,
    }));
    const missed = numbered.filter(({ question }) => !question.is_correct);
    const [showAll, setShowAll] = useState(false);
    const visible = showAll ? numbered : missed;

    return (
        <div className="animate-rise space-y-4">
            <div className="surface-tray">
                <div className="surface-core flex flex-col items-center gap-4 p-8 text-center">
                    <ScoreRing score={result.score} passed={passed} />
                    <div className="space-y-1">
                        <h2 className="text-lg font-semibold">
                            {result.score === 100
                                ? 'Perfect score!'
                                : passed
                                  ? 'You passed!'
                                  : 'Not quite there yet'}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            You got{' '}
                            <span className="font-medium text-foreground tabular-nums">
                                {result.correct_count} of{' '}
                                {result.questions_count}
                            </span>{' '}
                            questions right. The pass mark is {PASSING_SCORE}%.
                        </p>
                    </div>
                    <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <CheckCircle2 className="size-3.5 text-emerald-500" />
                        Your answers were sent to the training team.
                    </p>
                </div>
            </div>

            {missed.length > 0 ? (
                <div className="flex items-center justify-between gap-3 px-1">
                    <div>
                        <h3 className="text-sm font-semibold">
                            {showAll
                                ? 'All questions'
                                : `Review what you missed (${missed.length})`}
                        </h3>
                        <p className="text-xs text-muted-foreground">
                            {showAll
                                ? 'Your answers next to the correct ones.'
                                : 'See the correct answer and why, so you know what to brush up on.'}
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        className="shrink-0"
                        onClick={() => setShowAll((value) => !value)}
                    >
                        {showAll ? 'Only missed' : 'Show all'}
                    </Button>
                </div>
            ) : (
                <div className="flex flex-col items-center gap-3 rounded-xl border border-emerald-500/25 bg-emerald-500/5 p-4 text-center">
                    <p className="flex items-center gap-2 text-sm font-medium text-emerald-700 dark:text-emerald-400">
                        <PartyPopper className="size-4" />
                        Every answer was correct. Nice work!
                    </p>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => setShowAll((value) => !value)}
                    >
                        <RotateCcw className="size-4" />
                        {showAll ? 'Hide my answers' : 'Review my answers'}
                    </Button>
                </div>
            )}

            {visible.map(({ question, number }) => (
                <QuizQuestionResult
                    key={question.id}
                    question={question}
                    number={number}
                    viewer="trainee"
                />
            ))}

            <div className="surface-tray">
                <div className="surface-core flex flex-col items-center gap-3 p-5 text-center">
                    <p className="text-sm text-muted-foreground">
                        Finished reviewing? Confirm to close this quiz link.
                    </p>
                    <FinishDialog onConfirm={onFinish} processing={finishing} />
                </div>
            </div>
        </div>
    );
}

/** A circular gauge with the score in the middle. */
function ScoreRing({ score, passed }: { score: number; passed: boolean }) {
    const radius = 42;
    const circumference = 2 * Math.PI * radius;
    const offset =
        circumference * (1 - Math.min(Math.max(score, 0), 100) / 100);

    return (
        <div className="relative size-32">
            <svg viewBox="0 0 100 100" className="size-full -rotate-90">
                <circle
                    cx="50"
                    cy="50"
                    r={radius}
                    fill="none"
                    strokeWidth="8"
                    className="stroke-muted"
                />
                <circle
                    cx="50"
                    cy="50"
                    r={radius}
                    fill="none"
                    strokeWidth="8"
                    strokeLinecap="round"
                    strokeDasharray={circumference}
                    strokeDashoffset={offset}
                    className={cn(
                        'transition-[stroke-dashoffset] duration-700',
                        passed ? 'stroke-emerald-500' : 'stroke-destructive',
                    )}
                />
            </svg>
            <span
                className={cn(
                    'absolute inset-0 flex items-center justify-center text-3xl font-semibold tabular-nums',
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

function FinishDialog({
    onConfirm,
    processing,
}: {
    onConfirm: () => void;
    processing: boolean;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="lg" className="w-full sm:w-auto">
                    <CheckCircle2 className="size-4" /> I've reviewed my results
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Close this quiz link?</DialogTitle>
                    <DialogDescription>
                        Once you confirm, this link closes and your results and
                        the correct answers can't be opened again. Your score
                        stays with the training team.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        Keep reviewing
                    </Button>
                    <Button disabled={processing} onClick={onConfirm}>
                        <LockKeyhole className="size-4" /> Yes, close the link
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
