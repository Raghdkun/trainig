import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, Circle } from 'lucide-react';
import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { LocalTime } from '@/components/local-time';
import {
    StationAssessmentForm,
    countUnanswered,
} from '@/components/training/station-assessment-form';
import type { AssessmentAnswers } from '@/components/training/station-assessment-form';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import {
    index as developmentZoneIndex,
    reassess,
    show,
} from '@/routes/development-zone';
import { reassess as storeReassessment } from '@/routes/trainees/development';
import type { BreadcrumbItem } from '@/types';
import type { AssessmentSkillForm, StoreOption } from '@/types/training';

type ReassessProps = {
    trainee: {
        id: number;
        name: string;
        position: string | null;
        store: StoreOption;
    };
    skills: AssessmentSkillForm[];
    previousStars: Record<number, number>;
    previousAssessedAt: string | null;
};

const NOTES_LIMIT = 2000;

/**
 * The training team answers the station questions again to measure how far
 * the employee has come. Each station shows its last rating alongside the
 * new one; the result appears as before → after on the detail page.
 */
export default function DevelopmentZoneReassess() {
    const { trainee, skills, previousStars, previousAssessedAt } =
        usePage<ReassessProps>().props;

    const form = useForm<{ notes: string; answers: AssessmentAnswers }>({
        notes: '',
        answers: {},
    });
    // "Answer this question" prompts only appear once a submit has been tried.
    const [attempted, setAttempted] = useState(false);
    const formRef = useRef<HTMLFormElement>(null);
    const totalQuestions = skills.flatMap((skill) => skill.questions).length;
    const unanswered = countUnanswered(skills, form.data.answers);

    const clientErrors: Record<string, string> = {};

    for (const question of skills.flatMap((skill) => skill.questions)) {
        if (form.data.answers[question.id] === undefined) {
            clientErrors[`answers.${question.id}`] = 'Answer this question.';
        }
    }

    const errors: Partial<Record<string, string>> = {
        ...(attempted ? clientErrors : {}),
        ...(form.errors as Partial<Record<string, string>>),
    };

    function setAnswer(questionId: number, value: number | undefined) {
        const next = { ...form.data.answers };

        if (value === undefined) {
            delete next[questionId];
        } else {
            next[questionId] = value;
        }

        form.setData('answers', next);
        // Per-question errors are keyed "answers.{id}", which the typed
        // field list doesn't cover.
        form.clearErrors(`answers.${questionId}` as 'answers');
    }

    function revealFirstProblem() {
        requestAnimationFrame(() => {
            formRef.current
                ?.querySelector<HTMLElement>('[data-invalid="true"]')
                ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    }

    function submit(event: FormEvent) {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        if (unanswered > 0) {
            setAttempted(true);
            revealFirstProblem();

            return;
        }

        form.post(storeReassessment(trainee.id).url, {
            onError: revealFirstProblem,
        });
    }

    return (
        <>
            <Head title={`Reassess ${trainee.name}`} />

            <div className="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                <Link
                    href={show(trainee.id).url}
                    className="flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" /> {trainee.name}
                </Link>

                <header className="space-y-1.5">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Reassess {trainee.name}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {[trainee.position, trainee.store.name]
                            .filter(Boolean)
                            .join(' · ')}
                    </p>
                    {previousAssessedAt && (
                        <p className="text-sm text-muted-foreground">
                            Last assessed{' '}
                            <LocalTime
                                iso={previousAssessedAt}
                                variant="relative"
                            />
                            . Each station shows its last rating for comparison.
                        </p>
                    )}
                </header>

                <form
                    ref={formRef}
                    onSubmit={submit}
                    noValidate
                    className="flex w-full flex-col gap-6"
                >
                    <StationAssessmentForm
                        skills={skills}
                        answers={form.data.answers}
                        onAnswer={setAnswer}
                        errors={errors}
                        previousStars={previousStars}
                    />
                    <InputError message={errors.answers} />

                    <Card className="gap-0 p-0">
                        <div className="flex items-start justify-between gap-3 border-b border-border/60 p-5 sm:px-6">
                            <div className="space-y-0.5">
                                <Label
                                    htmlFor="reassess-notes"
                                    className="text-base font-semibold tracking-tight"
                                >
                                    Notes
                                </Label>
                                <p className="text-sm text-muted-foreground">
                                    What changed since the last assessment?
                                </p>
                            </div>
                            <span className="rounded-full border px-2 py-0.5 text-xs text-muted-foreground">
                                Optional
                            </span>
                        </div>
                        <div className="grid gap-2 p-5 sm:p-6">
                            <Textarea
                                id="reassess-notes"
                                value={form.data.notes}
                                onChange={(e) =>
                                    form.setData('notes', e.target.value)
                                }
                                rows={3}
                                maxLength={NOTES_LIMIT}
                                placeholder="e.g. Much faster on the make line; still double-checks the oven timer."
                            />
                            <div className="flex items-start justify-between gap-3">
                                <InputError message={errors.notes} />
                                <span className="ml-auto text-xs text-muted-foreground tabular-nums">
                                    {form.data.notes.length} / {NOTES_LIMIT}
                                </span>
                            </div>
                        </div>
                    </Card>

                    <div className="sticky bottom-4 z-10 flex flex-col gap-3 rounded-xl border bg-card/95 p-3 shadow-lg backdrop-blur supports-[backdrop-filter]:bg-card/80 sm:flex-row sm:items-center sm:justify-between sm:pl-5">
                        <p
                            className={
                                unanswered > 0 && attempted
                                    ? 'flex items-center gap-1.5 text-sm text-red-600 dark:text-red-400'
                                    : 'flex items-center gap-1.5 text-sm text-muted-foreground'
                            }
                        >
                            {unanswered === 0 ? (
                                <CheckCircle2 className="size-4 text-foreground" />
                            ) : (
                                <Circle className="size-4" />
                            )}
                            <span className="tabular-nums">
                                {totalQuestions - unanswered} of{' '}
                                {totalQuestions} answered
                            </span>
                        </p>
                        <div className="flex gap-2 sm:shrink-0">
                            <Button
                                variant="ghost"
                                asChild
                                className="flex-1 sm:flex-none"
                            >
                                <Link href={show(trainee.id).url}>Cancel</Link>
                            </Button>
                            <Button
                                type="submit"
                                disabled={form.processing}
                                className="flex-1 sm:flex-none"
                            >
                                {form.processing && <Spinner />}
                                Save reassessment
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        </>
    );
}

DevelopmentZoneReassess.layout = (page: ReassessProps) => ({
    breadcrumbs: [
        { title: 'Development Zone', href: developmentZoneIndex() },
        { title: page.trainee.name, href: show(page.trainee.id) },
        { title: 'Reassess', href: reassess(page.trainee.id) },
    ] satisfies BreadcrumbItem[],
});
