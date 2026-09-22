import { useForm } from '@inertiajs/react';
import { Check, Circle, ListChecks } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import { store, update } from '@/routes/training/quiz-questions';
import type { QuizQuestion, QuizQuestionType } from '@/types/training';

const OPTION_LETTERS = ['A', 'B', 'C', 'D'];
const MIN_MULTI_CORRECT = 2;
const MAX_MULTI_CORRECT = 3;

const TOGGLE_ACTIVE_CLASS =
    'data-[state=on]:border-primary/50 data-[state=on]:bg-primary/10 data-[state=on]:text-primary';

export function QuizQuestionFormDialog({
    quizId,
    question,
    trigger,
}: {
    quizId?: number;
    question?: QuizQuestion;
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);

    const sortedOptions = question?.options
        ?.slice()
        .sort((a, b) => a.order - b.order);

    const form = useForm({
        prompt: question?.prompt ?? '',
        type: (question?.type ?? 'single') as QuizQuestionType,
        options: sortedOptions?.map((option) => option.text) ?? [
            '',
            '',
            '',
            '',
        ],
        correct: sortedOptions
            ? sortedOptions.reduce<number[]>(
                  (indexes, option, index) =>
                      option.is_correct ? [...indexes, index] : indexes,
                  [],
              )
            : [0],
    });

    function setOptionText(index: number, text: string) {
        const next = [...form.data.options];
        next[index] = text;
        form.setData('options', next);
    }

    function changeType(type: QuizQuestionType) {
        form.setData({
            ...form.data,
            type,
            correct:
                type === 'single'
                    ? form.data.correct.slice(0, 1)
                    : form.data.correct,
        });
    }

    function toggleCorrect(index: number) {
        if (form.data.type === 'single') {
            form.setData('correct', [index]);

            return;
        }

        const isSelected = form.data.correct.includes(index);
        form.setData(
            'correct',
            isSelected
                ? form.data.correct.filter((i) => i !== index)
                : [...form.data.correct, index],
        );
    }

    const isSingle = form.data.type === 'single';
    const correctCount = form.data.correct.length;
    const canSave = isSingle
        ? correctCount === 1
        : correctCount >= MIN_MULTI_CORRECT &&
          correctCount <= MAX_MULTI_CORRECT;

    function submit(event: FormEvent) {
        event.preventDefault();

        form.transform((data) => ({
            prompt: data.prompt,
            type: data.type,
            options: data.options,
            ...(data.type === 'single'
                ? { correct_index: data.correct[0] ?? 0 }
                : { correct_indexes: data.correct }),
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };

        if (question) {
            form.put(update(question.id).url, options);
        } else if (quizId) {
            form.post(store(quizId).url, options);
        }
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {question ? 'Edit question' : 'New question'}
                    </DialogTitle>
                    <DialogDescription>
                        A short multiple-choice check trainees answer after this
                        station.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-5">
                    <div className="grid gap-2">
                        <Label htmlFor="prompt">Question</Label>
                        <Input
                            id="prompt"
                            value={form.data.prompt}
                            onChange={(e) =>
                                form.setData('prompt', e.target.value)
                            }
                            placeholder="e.g. What temperature should the walk-in be?"
                            autoFocus
                            required
                        />
                        <InputError message={form.errors.prompt} />
                    </div>

                    <div className="grid gap-2">
                        <Label>Answer type</Label>
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            value={form.data.type}
                            onValueChange={(value) =>
                                value && changeType(value as QuizQuestionType)
                            }
                            className="w-full"
                        >
                            <ToggleGroupItem
                                value="single"
                                className={cn(
                                    'flex-1 gap-1.5',
                                    TOGGLE_ACTIVE_CLASS,
                                )}
                            >
                                <Circle className="size-4" /> Single answer
                            </ToggleGroupItem>
                            <ToggleGroupItem
                                value="multi"
                                className={cn(
                                    'flex-1 gap-1.5',
                                    TOGGLE_ACTIVE_CLASS,
                                )}
                            >
                                <ListChecks className="size-4" /> Multiple
                                answers
                            </ToggleGroupItem>
                        </ToggleGroup>
                        <p className="text-xs text-muted-foreground">
                            {isSingle ? (
                                'Trainees pick exactly one correct answer.'
                            ) : (
                                <>
                                    Trainees must select every correct answer to
                                    get credit.{' '}
                                    <span
                                        className={cn(
                                            'font-medium',
                                            canSave
                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                : 'text-amber-600 dark:text-amber-400',
                                        )}
                                    >
                                        Mark {MIN_MULTI_CORRECT}–
                                        {MAX_MULTI_CORRECT} as correct (
                                        {correctCount} selected).
                                    </span>
                                </>
                            )}
                        </p>
                    </div>

                    <div className="grid gap-2">
                        <Label>Answers</Label>
                        {form.data.options.map((text, index) => {
                            const isCorrect = form.data.correct.includes(index);

                            return (
                                <div
                                    key={index}
                                    className={cn(
                                        'flex items-center gap-2 rounded-lg border p-1 pl-1.5 transition-colors',
                                        isCorrect
                                            ? 'border-emerald-500/50 bg-emerald-500/5'
                                            : 'border-border',
                                    )}
                                >
                                    <button
                                        type="button"
                                        onClick={() => toggleCorrect(index)}
                                        aria-pressed={isCorrect}
                                        aria-label={`Mark option ${OPTION_LETTERS[index]} as correct`}
                                        className={cn(
                                            'flex size-7 shrink-0 items-center justify-center text-xs font-semibold transition-colors',
                                            isSingle
                                                ? 'rounded-full'
                                                : 'rounded-md',
                                            isCorrect
                                                ? 'bg-emerald-500 text-white'
                                                : 'bg-muted text-muted-foreground hover:bg-accent',
                                        )}
                                    >
                                        {OPTION_LETTERS[index]}
                                    </button>
                                    <Input
                                        value={text}
                                        onChange={(e) =>
                                            setOptionText(index, e.target.value)
                                        }
                                        placeholder={`Option ${OPTION_LETTERS[index]}`}
                                        required
                                        className="h-8 border-0 bg-transparent px-1 shadow-none focus-visible:ring-0"
                                    />
                                    {isCorrect && (
                                        <Check className="size-4 shrink-0 text-emerald-500" />
                                    )}
                                </div>
                            );
                        })}
                        <InputError message={form.errors.options} />
                        <InputError
                            message={
                                (
                                    form.errors as Record<
                                        string,
                                        string | undefined
                                    >
                                ).correct_index
                            }
                        />
                        <InputError
                            message={
                                (
                                    form.errors as Record<
                                        string,
                                        string | undefined
                                    >
                                ).correct_indexes
                            }
                        />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing || !canSave}
                        >
                            Save question
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
