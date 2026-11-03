import {
    CheckCircle2,
    CircleDot,
    Lightbulb,
    ListChecks,
    XCircle,
} from 'lucide-react';
import { OPTION_LETTERS } from '@/lib/quiz';
import { cn } from '@/lib/utils';
import type { QuizResultOption, QuizResultQuestion } from '@/types/training';

/** Who is reading the result — changes "Their answer" to "Your answer". */
type Viewer = 'admin' | 'trainee';

/**
 * One answered question: every option marked as chosen / correct / missed,
 * plus the author's explanation when there is one. Shared by the training
 * team's result page and the trainee's own results so both read the same.
 */
export function QuizQuestionResult({
    question,
    number,
    viewer,
}: {
    question: QuizResultQuestion;
    number: number;
    viewer: Viewer;
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
                            viewer={viewer}
                        />
                    ))}
                </div>

                {question.explanation && (
                    <div
                        className={cn(
                            'mt-4 flex items-start gap-2.5 rounded-lg border p-3 text-sm',
                            question.is_correct
                                ? 'border-border/60 bg-muted/40'
                                : 'border-primary/25 bg-primary/5',
                        )}
                    >
                        <Lightbulb className="mt-0.5 size-4 shrink-0 text-primary" />
                        <div className="min-w-0">
                            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Why
                            </p>
                            <p className="mt-0.5 text-pretty whitespace-pre-line">
                                {question.explanation}
                            </p>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

function OptionResult({
    option,
    letter,
    viewer,
}: {
    option: QuizResultOption;
    letter: string;
    viewer: Viewer;
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
                    {viewer === 'trainee' ? 'Your answer' : 'Their answer'}
                </span>
            )}
            {missed && (
                <span className="shrink-0 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                    {viewer === 'trainee' ? 'Correct answer' : 'Missed'}
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
