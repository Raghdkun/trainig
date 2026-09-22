import { router } from '@inertiajs/react';
import {
    CheckCircle2,
    FileQuestion,
    Info,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { ConfirmDeleteDialog } from '@/components/training/confirm-delete-dialog';
import { QuizQuestionFormDialog } from '@/components/training/quiz-question-form-dialog';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { destroy as destroyQuestion } from '@/routes/training/quiz-questions';
import {
    destroy as destroyQuiz,
    store as enableQuiz,
} from '@/routes/training/quizzes';
import type { Quiz } from '@/types/training';

const MAX_QUESTIONS = 5;
const MIN_TO_SEND = 3;

/**
 * The optional quiz block on a station's builder page. A station has at
 * most one quiz, capped at 5 short multiple-choice questions.
 */
export function QuizManager({
    sectionId,
    quiz,
}: {
    sectionId: number;
    quiz: Quiz | null;
}) {
    if (!quiz) {
        return (
            <Card className="flex flex-col items-center gap-3 border-dashed p-6 text-center">
                <FileQuestion className="size-8 text-muted-foreground" />
                <div>
                    <p className="text-sm font-medium">No quiz yet</p>
                    <p className="text-sm text-muted-foreground">
                        Add a short 3–5 question check trainees complete after
                        this station.
                    </p>
                </div>
                <Button
                    size="sm"
                    onClick={() =>
                        router.post(
                            enableQuiz(sectionId).url,
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    <Plus className="size-4" /> Add quiz
                </Button>
            </Card>
        );
    }

    return (
        <section className="surface-tray">
            <div className="surface-core overflow-hidden">
                <header className="flex flex-wrap items-center justify-between gap-3 border-b border-border/60 p-4">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary ring-1 ring-primary/15">
                            <FileQuestion
                                className="size-4"
                                strokeWidth={1.75}
                            />
                        </div>
                        <div>
                            <h3 className="font-semibold tracking-tight">
                                Quiz
                            </h3>
                            <div className="flex items-center gap-1.5">
                                <div className="flex items-center gap-0.5">
                                    {Array.from({ length: MAX_QUESTIONS }).map(
                                        (_, index) => (
                                            <span
                                                key={index}
                                                className={cn(
                                                    'size-1.5 rounded-full',
                                                    index <
                                                        quiz.questions.length
                                                        ? 'bg-primary'
                                                        : 'bg-muted',
                                                )}
                                            />
                                        ),
                                    )}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {quiz.questions.length}/{MAX_QUESTIONS}{' '}
                                    questions
                                </p>
                            </div>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <QuizQuestionFormDialog
                            quizId={quiz.id}
                            trigger={
                                <Button
                                    size="sm"
                                    variant="outline"
                                    disabled={
                                        quiz.questions.length >= MAX_QUESTIONS
                                    }
                                >
                                    <Plus className="size-4" /> Add question
                                </Button>
                            }
                        />
                        <ConfirmDeleteDialog
                            title="Remove quiz?"
                            description="This permanently deletes the quiz, its questions, and any sent or completed results for it."
                            onConfirm={(close) =>
                                router.delete(destroyQuiz(quiz.id).url, {
                                    preserveScroll: true,
                                    onSuccess: close,
                                })
                            }
                            trigger={
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    className="text-muted-foreground hover:text-destructive"
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            }
                        />
                    </div>
                </header>

                {quiz.questions.length === 0 ? (
                    <p className="p-6 text-center text-sm text-muted-foreground">
                        No questions yet. Add at least {MIN_TO_SEND} to make
                        this quiz sendable.
                    </p>
                ) : (
                    <ul className="grid gap-2 p-4">
                        {quiz.questions.map((question, index) => (
                            <li
                                key={question.id}
                                className="group flex items-start gap-3 rounded-lg border border-border/60 p-3 transition-colors hover:border-primary/30 hover:bg-muted/30"
                            >
                                <span className="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-md bg-muted text-xs font-semibold text-muted-foreground tabular-nums">
                                    {index + 1}
                                </span>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-1.5">
                                        <p className="text-sm font-medium">
                                            {question.prompt}
                                        </p>
                                        {question.type === 'multi' && (
                                            <span className="shrink-0 rounded-full border border-border/60 px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground uppercase">
                                                Multi
                                            </span>
                                        )}
                                    </div>
                                    <p className="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
                                        <CheckCircle2 className="size-3.5 shrink-0 text-emerald-500" />
                                        <span className="truncate">
                                            {question.options
                                                .filter(
                                                    (option) =>
                                                        option.is_correct,
                                                )
                                                .map((option) => option.text)
                                                .join(' · ')}
                                        </span>
                                    </p>
                                </div>
                                <div className="flex shrink-0 gap-1 opacity-0 transition-opacity group-focus-within:opacity-100 group-hover:opacity-100">
                                    <QuizQuestionFormDialog
                                        question={question}
                                        trigger={
                                            <Button size="icon" variant="ghost">
                                                <Pencil className="size-4" />
                                            </Button>
                                        }
                                    />
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        className="text-muted-foreground hover:text-destructive"
                                        onClick={() =>
                                            router.delete(
                                                destroyQuestion(question.id)
                                                    .url,
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                {quiz.questions.length > 0 &&
                    quiz.questions.length < MIN_TO_SEND && (
                        <div className="flex items-center gap-2 border-t border-border/60 bg-muted/30 px-4 py-2.5 text-xs text-muted-foreground">
                            <Info className="size-3.5 shrink-0" /> Add at least{' '}
                            {MIN_TO_SEND} questions before sending this quiz to
                            trainees.
                        </div>
                    )}
            </div>
        </section>
    );
}
