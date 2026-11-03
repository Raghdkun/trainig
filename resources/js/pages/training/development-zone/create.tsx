import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    CheckCircle2,
    Circle,
    Store as StoreIcon,
    UserCheck,
    UserPlus,
} from 'lucide-react';
import { useRef, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { DatePicker } from '@/components/date-picker';
import InputError from '@/components/input-error';
import { GRADE_TONES } from '@/components/training/evaluation-grade';
import {
    StationAssessmentForm,
    countUnanswered,
} from '@/components/training/station-assessment-form';
import type { AssessmentAnswers } from '@/components/training/station-assessment-form';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { create, index } from '@/routes/development-zone';
import { store as storeEmployee } from '@/routes/development-zone/employees';
import { store } from '@/routes/trainees/development';
import type { BreadcrumbItem } from '@/types';
import type {
    AddableTrainee,
    AssessmentSkillForm,
    EvaluationGrade,
    PositionOption,
    StoreOption,
} from '@/types/training';

type Mode = 'existing' | 'new';

type FormData = {
    name: string;
    hired_at: string;
    position: string;
    store_id: string;
    grade: EvaluationGrade | '';
    points: string;
    notes: string;
    answers: AssessmentAnswers;
};

const NOTES_LIMIT = 2000;

const SECTION_IDS = {
    employee: 'dev-zone-employee',
    evaluation: 'dev-zone-evaluation',
    assessment: 'dev-zone-assessment',
} as const;

/** Today as "YYYY-MM-DD" in local time — a hire date can't be later. */
function todayValue(): string {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${now.getFullYear()}-${month}-${day}`;
}

/**
 * Brings someone into the Development Zone with the manager's evaluation —
 * either a trainee already on the roster, or a brand-new employee entered
 * here (who then stays off the Trainees roster). Either way they land as
 * Pending until an admin reviews the evaluation and builds their plan.
 */
export default function DevelopmentZoneCreate() {
    const {
        addableTrainees,
        selectedTraineeId,
        skills,
        canAddEmployee,
        employeeStores,
        positionOptions,
        gradeOptions,
    } = usePage<{
        addableTrainees: AddableTrainee[];
        selectedTraineeId: number | null;
        skills: AssessmentSkillForm[];
        canAddEmployee: boolean;
        employeeStores: StoreOption[];
        positionOptions: PositionOption[];
        gradeOptions: EvaluationGrade[];
    }>().props;

    const canPickExisting = addableTrainees.length > 0;
    const [mode, setMode] = useState<Mode>(
        canPickExisting || !canAddEmployee ? 'existing' : 'new',
    );
    const [traineeId, setTraineeId] = useState<number | null>(
        selectedTraineeId,
    );
    // Required-field messages only appear once a submit has been tried.
    const [attempted, setAttempted] = useState(false);
    const formRef = useRef<HTMLFormElement>(null);
    const form = useForm<FormData>({
        name: '',
        hired_at: '',
        position: '',
        store_id: '',
        grade: '',
        points: '',
        notes: '',
        answers: {},
    });

    const chooseStore = employeeStores.length > 1;
    const autoStore = employeeStores.length === 1 ? employeeStores[0] : null;
    const today = todayValue();

    /** Set a field and drop its server error, so stale messages don't linger. */
    function update<K extends keyof FormData>(field: K, value: FormData[K]) {
        form.setData((data) => ({ ...data, [field]: value }));
        form.clearErrors(field);
    }

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

    function chooseMode(next: Mode) {
        setMode(next);
        form.clearErrors();
    }

    const pointsText = form.data.points.trim();
    const points = Number(pointsText);
    const pointsValid =
        pointsText !== '' &&
        Number.isInteger(points) &&
        points >= 0 &&
        points <= 100;

    const clientErrors: Record<string, string> = {};

    if (mode === 'existing') {
        if (traineeId === null) {
            clientErrors.trainee = 'Please choose a trainee.';
        }
    } else {
        if (form.data.name.trim() === '') {
            clientErrors.name = 'Please enter the employee’s full name.';
        }

        if (form.data.hired_at === '') {
            clientErrors.hired_at = 'Please choose the hire date.';
        } else if (form.data.hired_at > today) {
            clientErrors.hired_at = 'The hire date can’t be in the future.';
        }

        if (form.data.position === '') {
            clientErrors.position = 'Please choose a position.';
        }

        if (chooseStore && form.data.store_id === '') {
            clientErrors.store_id = 'Please choose a store.';
        }
    }

    if (form.data.grade === '') {
        clientErrors.grade = 'Please choose an evaluation grade.';
    }

    if (!pointsValid) {
        clientErrors.points =
            pointsText === ''
                ? 'Please enter the overall points.'
                : 'Use a whole number from 0 to 100.';
    }

    for (const question of skills.flatMap((skill) => skill.questions)) {
        if (form.data.answers[question.id] === undefined) {
            clientErrors[`answers.${question.id}`] = 'Answer this question.';
        }
    }

    const errors: Partial<Record<string, string>> = {
        ...(attempted ? clientErrors : {}),
        ...(form.errors as Partial<Record<string, string>>),
    };

    const unanswered = countUnanswered(skills, form.data.answers);
    const employeeKeys = [
        'trainee',
        'name',
        'hired_at',
        'position',
        'store_id',
    ];
    const steps = [
        {
            id: SECTION_IDS.employee,
            label: 'Employee',
            done: !employeeKeys.some((key) => key in clientErrors),
            detail: null,
        },
        {
            id: SECTION_IDS.evaluation,
            label: 'Evaluation',
            done: !('grade' in clientErrors) && !('points' in clientErrors),
            detail: null,
        },
        {
            id: SECTION_IDS.assessment,
            label: 'Assessment',
            done: unanswered === 0,
            detail: unanswered > 0 ? `${unanswered} left` : null,
        },
    ];
    const isComplete = Object.keys(clientErrors).length === 0;

    function revealFirstProblem() {
        requestAnimationFrame(() => {
            const target = formRef.current?.querySelector<HTMLElement>(
                '[aria-invalid="true"], [data-invalid="true"]',
            );

            target?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            target?.focus({ preventScroll: true });
        });
    }

    function submit(event: FormEvent) {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        if (!isComplete) {
            setAttempted(true);
            revealFirstProblem();

            return;
        }

        const options = { onError: revealFirstProblem };

        if (mode === 'new') {
            form.post(storeEmployee().url, options);

            return;
        }

        if (traineeId) {
            form.post(store(traineeId).url, options);
        }
    }

    return (
        <>
            <Head title="Add to Development Zone" />

            <div className="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                <Link
                    href={index().url}
                    className="flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" /> Development Zone
                </Link>

                <header className="space-y-1.5">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Add to Development Zone
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Evaluate the employee’s current performance. An admin
                        will review it and build their development plan.
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Fields marked <RequiredMark /> are required.
                    </p>
                </header>

                <form
                    ref={formRef}
                    onSubmit={submit}
                    noValidate
                    className="flex w-full flex-col gap-6"
                >
                    <FormSection
                        id={SECTION_IDS.employee}
                        step={1}
                        title="Employee"
                        description="Who is being evaluated?"
                        done={steps[0].done}
                    >
                        {canAddEmployee && (
                            <div
                                role="radiogroup"
                                aria-label="Employee type"
                                className="grid gap-3 sm:grid-cols-2"
                            >
                                <ModeOption
                                    selected={mode === 'existing'}
                                    disabled={!canPickExisting}
                                    onSelect={() => chooseMode('existing')}
                                    icon={<UserCheck className="size-4" />}
                                    title="Existing trainee"
                                    description={
                                        canPickExisting
                                            ? 'Someone already on the Trainees roster.'
                                            : 'Every trainee is already in the Development Zone.'
                                    }
                                />
                                <ModeOption
                                    selected={mode === 'new'}
                                    onSelect={() => chooseMode('new')}
                                    icon={<UserPlus className="size-4" />}
                                    title="New employee"
                                    description="Not on the roster — they’ll only appear in the Development Zone."
                                />
                            </div>
                        )}

                        {mode === 'existing' ? (
                            <Field
                                id="dev-zone-trainee"
                                label="Trainee"
                                required
                                error={errors.trainee}
                                hint={
                                    !canPickExisting && !canAddEmployee
                                        ? 'Every trainee is already in the Development Zone.'
                                        : undefined
                                }
                            >
                                <Select
                                    value={
                                        traineeId
                                            ? String(traineeId)
                                            : undefined
                                    }
                                    onValueChange={(value) => {
                                        setTraineeId(Number(value));
                                        form.clearErrors();
                                    }}
                                >
                                    <SelectTrigger
                                        id="dev-zone-trainee"
                                        className="w-full"
                                        aria-invalid={Boolean(errors.trainee)}
                                    >
                                        <SelectValue placeholder="Choose a trainee" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {addableTrainees.map((trainee) => (
                                            <SelectItem
                                                key={trainee.id}
                                                value={String(trainee.id)}
                                            >
                                                {[
                                                    trainee.name,
                                                    trainee.position,
                                                    chooseStore
                                                        ? trainee.store.name
                                                        : null,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                        ) : (
                            <div className="grid gap-5">
                                <Field
                                    id="dev-zone-name"
                                    label="Full name"
                                    required
                                    error={errors.name}
                                >
                                    <Input
                                        id="dev-zone-name"
                                        value={form.data.name}
                                        onChange={(e) =>
                                            update('name', e.target.value)
                                        }
                                        placeholder="e.g. Jordan Lee"
                                        autoComplete="off"
                                        maxLength={255}
                                        required
                                        aria-invalid={Boolean(errors.name)}
                                    />
                                </Field>
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <Field
                                        id="dev-zone-hired-at"
                                        label="Hire date"
                                        required
                                        error={errors.hired_at}
                                    >
                                        <DatePicker
                                            id="dev-zone-hired-at"
                                            value={form.data.hired_at}
                                            onChange={(value) =>
                                                update('hired_at', value)
                                            }
                                            placeholder="Select a date"
                                            maxDate={new Date()}
                                            invalid={Boolean(errors.hired_at)}
                                        />
                                    </Field>
                                    <Field
                                        id="dev-zone-position"
                                        label="Full position"
                                        required
                                        error={errors.position}
                                    >
                                        <Select
                                            value={form.data.position}
                                            onValueChange={(value) =>
                                                update('position', value)
                                            }
                                        >
                                            <SelectTrigger
                                                id="dev-zone-position"
                                                className="w-full"
                                                aria-invalid={Boolean(
                                                    errors.position,
                                                )}
                                            >
                                                <SelectValue placeholder="Select a position" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {positionOptions.map(
                                                    (option) => (
                                                        <SelectItem
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </Field>
                                </div>
                                {chooseStore && (
                                    <Field
                                        id="dev-zone-store"
                                        label="Store"
                                        required
                                        error={errors.store_id}
                                    >
                                        <Select
                                            value={form.data.store_id}
                                            onValueChange={(value) =>
                                                update('store_id', value)
                                            }
                                        >
                                            <SelectTrigger
                                                id="dev-zone-store"
                                                className="w-full"
                                                aria-invalid={Boolean(
                                                    errors.store_id,
                                                )}
                                            >
                                                <SelectValue placeholder="Select a store" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {employeeStores.map((s) => (
                                                    <SelectItem
                                                        key={s.id}
                                                        value={String(s.id)}
                                                    >
                                                        {s.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </Field>
                                )}
                                {autoStore && (
                                    <div className="grid gap-2">
                                        <p className="flex items-center gap-2 rounded-md bg-muted/50 px-3 py-2 text-sm text-muted-foreground">
                                            <StoreIcon className="size-4 shrink-0" />
                                            <span>
                                                Will be added to{' '}
                                                <span className="font-medium text-foreground">
                                                    {autoStore.name}
                                                </span>
                                            </span>
                                        </p>
                                        <InputError message={errors.store_id} />
                                    </div>
                                )}
                            </div>
                        )}
                    </FormSection>

                    <FormSection
                        id={SECTION_IDS.evaluation}
                        step={2}
                        title="Overall evaluation"
                        description="Your overall judgment of their current performance."
                        done={steps[1].done}
                    >
                        <div className="grid gap-6 sm:grid-cols-2">
                            <Field
                                label="Employee evaluation"
                                required
                                error={errors.grade}
                                hint="A is the strongest, D the weakest."
                            >
                                <div
                                    role="radiogroup"
                                    aria-label="Employee evaluation grade"
                                    data-invalid={
                                        errors.grade ? true : undefined
                                    }
                                    className="grid grid-cols-4 gap-2"
                                >
                                    {gradeOptions.map((grade) => {
                                        const selected =
                                            form.data.grade === grade;

                                        return (
                                            <button
                                                key={grade}
                                                type="button"
                                                role="radio"
                                                aria-checked={selected}
                                                onClick={() =>
                                                    update(
                                                        'grade',
                                                        selected ? '' : grade,
                                                    )
                                                }
                                                className={cn(
                                                    'flex h-12 items-center justify-center rounded-md border text-lg font-semibold shadow-xs transition-colors outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30',
                                                    selected
                                                        ? GRADE_TONES[grade]
                                                              .selected
                                                        : 'text-muted-foreground hover:border-foreground/30 hover:text-foreground',
                                                    errors.grade &&
                                                        !selected &&
                                                        'border-destructive/60',
                                                )}
                                            >
                                                {grade}
                                            </button>
                                        );
                                    })}
                                </div>
                            </Field>
                            <Field
                                id="dev-zone-points"
                                label="Overall points"
                                required
                                error={errors.points}
                                hint="A whole number from 0 to 100."
                            >
                                <div className="relative">
                                    <Input
                                        id="dev-zone-points"
                                        type="number"
                                        inputMode="numeric"
                                        min={0}
                                        max={100}
                                        step={1}
                                        value={form.data.points}
                                        onChange={(e) =>
                                            update('points', e.target.value)
                                        }
                                        placeholder="e.g. 72"
                                        required
                                        className="h-12 [appearance:textfield] pr-14 text-lg font-semibold tabular-nums placeholder:text-base placeholder:font-normal [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                        aria-invalid={Boolean(errors.points)}
                                    />
                                    <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-muted-foreground">
                                        / 100
                                    </span>
                                </div>
                                <div
                                    className="h-1 overflow-hidden rounded-full bg-muted"
                                    aria-hidden
                                >
                                    <div
                                        className="h-full rounded-full bg-foreground/60 transition-[width] duration-300"
                                        style={{
                                            width: `${pointsValid ? points : 0}%`,
                                        }}
                                    />
                                </div>
                            </Field>
                        </div>
                    </FormSection>

                    <section
                        id={SECTION_IDS.assessment}
                        className="flex scroll-mt-6 flex-col gap-4"
                    >
                        <SectionHeading
                            step={3}
                            title="Stations & skills assessment"
                            description="Answer each station’s questions. The answers give a star rating per station, and the lowest skills become this employee’s development needs."
                            done={steps[2].done}
                            aside={
                                skills.length > 0 && (
                                    <span className="text-xs whitespace-nowrap text-muted-foreground tabular-nums">
                                        {unanswered === 0
                                            ? 'All answered'
                                            : `${unanswered} unanswered`}
                                    </span>
                                )
                            }
                            className="px-1"
                        />
                        <StationAssessmentForm
                            skills={skills}
                            answers={form.data.answers}
                            onAnswer={setAnswer}
                            errors={errors}
                        />
                    </section>

                    <FormSection
                        step={4}
                        title="Notes"
                        description="Anything the admin should know when building this plan."
                        optional
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="dev-zone-notes" className="sr-only">
                                Notes
                            </Label>
                            <Textarea
                                id="dev-zone-notes"
                                value={form.data.notes}
                                onChange={(e) =>
                                    update('notes', e.target.value)
                                }
                                rows={4}
                                maxLength={NOTES_LIMIT}
                                placeholder="e.g. Strong with customers, but slow on the make line during rush."
                            />
                            <div className="flex items-start justify-between gap-3">
                                <InputError message={errors.notes} />
                                <span className="ml-auto text-xs text-muted-foreground tabular-nums">
                                    {form.data.notes.length} / {NOTES_LIMIT}
                                </span>
                            </div>
                        </div>
                    </FormSection>

                    <div className="sticky bottom-4 z-10 flex flex-col gap-3 rounded-xl border bg-card/95 p-3 shadow-lg backdrop-blur supports-[backdrop-filter]:bg-card/80 sm:flex-row sm:items-center sm:justify-between sm:pl-5">
                        <ol className="flex flex-wrap items-center gap-x-5 gap-y-1.5 text-sm">
                            {steps.map((step) => (
                                <li key={step.id}>
                                    <a
                                        href={`#${step.id}`}
                                        className={cn(
                                            'flex items-center gap-1.5 transition-colors hover:text-foreground',
                                            step.done
                                                ? 'text-foreground'
                                                : attempted
                                                  ? 'text-red-600 dark:text-red-400'
                                                  : 'text-muted-foreground',
                                        )}
                                    >
                                        {step.done ? (
                                            <CheckCircle2 className="size-4" />
                                        ) : (
                                            <Circle className="size-4" />
                                        )}
                                        {step.label}
                                        {step.detail && (
                                            <span className="text-xs text-muted-foreground tabular-nums">
                                                · {step.detail}
                                            </span>
                                        )}
                                    </a>
                                </li>
                            ))}
                        </ol>
                        <div className="flex gap-2 sm:shrink-0">
                            <Button
                                variant="ghost"
                                asChild
                                className="flex-1 sm:flex-none"
                            >
                                <Link href={index().url}>Cancel</Link>
                            </Button>
                            <Button
                                type="submit"
                                disabled={form.processing}
                                className="flex-1 sm:flex-none"
                            >
                                {form.processing && <Spinner />}
                                Submit evaluation
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        </>
    );
}

function RequiredMark() {
    return (
        <span aria-hidden className="ml-0.5 text-red-500">
            *
        </span>
    );
}

function Field({
    id,
    label,
    required = false,
    error,
    hint,
    children,
}: {
    id?: string;
    label: string;
    required?: boolean;
    error?: string;
    hint?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid content-start gap-2">
            <Label htmlFor={id}>
                {label}
                {required && <RequiredMark />}
                {required && <span className="sr-only"> (required)</span>}
            </Label>
            {children}
            {error ? (
                <InputError message={error} />
            ) : (
                hint && <p className="text-xs text-muted-foreground">{hint}</p>
            )}
        </div>
    );
}

function StepMarker({ step, done }: { step: number; done: boolean }) {
    return (
        <span
            className={cn(
                'flex size-7 shrink-0 items-center justify-center rounded-full border text-xs font-semibold tabular-nums transition-colors',
                done
                    ? 'border-foreground/40 text-foreground'
                    : 'text-muted-foreground',
            )}
            aria-hidden
        >
            {done ? <Check className="size-3.5" strokeWidth={3} /> : step}
        </span>
    );
}

function SectionHeading({
    step,
    title,
    description,
    done = false,
    optional = false,
    aside,
    className,
}: {
    step: number;
    title: string;
    description?: string;
    done?: boolean;
    optional?: boolean;
    aside?: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('flex items-start gap-3', className)}>
            <StepMarker step={step} done={done} />
            <div className="min-w-0 flex-1 space-y-0.5">
                <h2 className="leading-7 font-semibold tracking-tight">
                    {title}
                </h2>
                {description && (
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {optional ? (
                <span className="rounded-full border px-2 py-0.5 text-xs text-muted-foreground">
                    Optional
                </span>
            ) : (
                aside
            )}
        </div>
    );
}

function FormSection({
    id,
    step,
    title,
    description,
    done = false,
    optional = false,
    children,
}: {
    id?: string;
    step: number;
    title: string;
    description?: string;
    done?: boolean;
    optional?: boolean;
    children: ReactNode;
}) {
    return (
        <Card id={id} className="scroll-mt-6 gap-0 p-0">
            <SectionHeading
                step={step}
                title={title}
                description={description}
                done={done}
                optional={optional}
                className="border-b border-border/60 p-5 sm:px-6"
            />
            <div className="grid gap-5 p-5 sm:p-6">{children}</div>
        </Card>
    );
}

function ModeOption({
    selected,
    disabled = false,
    onSelect,
    icon,
    title,
    description,
}: {
    selected: boolean;
    disabled?: boolean;
    onSelect: () => void;
    icon: ReactNode;
    title: string;
    description: string;
}) {
    return (
        <button
            type="button"
            role="radio"
            aria-checked={selected}
            disabled={disabled}
            onClick={onSelect}
            className={cn(
                'flex items-start gap-3 rounded-lg border p-4 text-left transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50',
                selected ? 'border-primary' : 'hover:border-foreground/30',
            )}
        >
            <span
                className={cn(
                    'flex size-9 shrink-0 items-center justify-center rounded-md border transition-colors',
                    selected
                        ? 'border-primary/60 text-primary'
                        : 'text-muted-foreground',
                )}
            >
                {icon}
            </span>
            <span className="min-w-0 space-y-0.5">
                <span className="block text-sm font-medium">{title}</span>
                <span className="block text-xs text-muted-foreground">
                    {description}
                </span>
            </span>
        </button>
    );
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Development Zone', href: index() },
    { title: 'Add', href: create() },
];

DevelopmentZoneCreate.layout = { breadcrumbs };
