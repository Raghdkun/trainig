import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    BookOpen,
    CheckCircle2,
    ChevronRight,
    CircleSlash,
    ClipboardCheck,
    ListChecks,
    Pencil,
    Plus,
    Star,
    Target,
    Trash2,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Fragment, useState } from 'react';
import {
    ANSWER_TYPE_META,
    QuestionFormDialog,
    SkillFormDialog,
} from '@/components/training/assessment-setup-dialogs';
import type {
    AnswerTypeOption,
    SectionOption,
} from '@/components/training/assessment-setup-dialogs';
import { ConfirmDeleteDialog } from '@/components/training/confirm-delete-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { index as developmentZoneIndex } from '@/routes/development-zone';
import { assessmentSetup } from '@/routes/training';
import { destroy as destroyQuestion } from '@/routes/training/assessment-questions';
import {
    destroy as destroySkill,
    update as updateSkill,
} from '@/routes/training/assessment-skills';
import type { BreadcrumbItem } from '@/types';
import type { AssessmentSkillSetup } from '@/types/training';

const HOW_IT_WORKS: { icon: LucideIcon; title: string; text: string }[] = [
    {
        icon: ListChecks,
        title: 'Answer the questions',
        text: 'Yes / No, a Level from 1–5, or a Percentage.',
    },
    {
        icon: Star,
        title: 'Get a star rating',
        text: 'Answers average into 0–5 stars per station or skill.',
    },
    {
        icon: Target,
        title: 'Find development needs',
        text: 'Under 3★ is a need — or, if none are, the lowest-rated. Linked training is suggested.',
    },
];

/**
 * Admin setup for the Development Zone assessment: the stations & skills
 * employees are rated on, each with its own questions.
 */
export default function AssessmentSetup() {
    const { skills, sectionOptions, answerTypeOptions } = usePage<{
        skills: AssessmentSkillSetup[];
        sectionOptions: SectionOption[];
        answerTypeOptions: AnswerTypeOption[];
    }>().props;

    const askedCount = skills.filter(
        (skill) => skill.is_active && skill.questions.length > 0,
    ).length;
    const questionCount = skills
        .filter((skill) => skill.is_active)
        .reduce((sum, skill) => sum + skill.questions.length, 0);
    const needQuestions = skills.filter(
        (skill) => skill.is_active && skill.questions.length === 0,
    );

    const addSkillButton = (
        <SkillFormDialog
            sectionOptions={sectionOptions}
            trigger={
                <Button className="shrink-0">
                    <Plus className="size-4" /> Add station or skill
                </Button>
            }
        />
    );

    return (
        <>
            <Head title="Assessment setup" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <Link
                    href={developmentZoneIndex().url}
                    className="flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" /> Development Zone
                </Link>

                <header className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-1.5">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Assessment setup
                        </h1>
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            Choose what employees are rated on in the
                            Development Zone — stations like Making, or skills
                            like Cleaning and Communication.
                        </p>
                    </div>
                    {addSkillButton}
                </header>

                <Card className="gap-0 p-0">
                    <p className="border-b border-border/60 px-5 py-2.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        How ratings work
                    </p>
                    <ol className="grid md:grid-cols-[1fr_auto_1fr_auto_1fr]">
                        {HOW_IT_WORKS.map((step, index) => (
                            <Fragment key={step.title}>
                                {index > 0 && (
                                    <li
                                        aria-hidden
                                        className="hidden items-center text-muted-foreground/50 md:flex"
                                    >
                                        <ChevronRight className="size-4" />
                                    </li>
                                )}
                                <li className="flex items-start gap-3 px-5 py-4 not-first:border-t md:not-first:border-t-0">
                                    <span className="flex size-8 shrink-0 items-center justify-center rounded-full border text-muted-foreground">
                                        <step.icon className="size-4" />
                                    </span>
                                    <div className="space-y-0.5">
                                        <p className="text-sm font-medium">
                                            <span className="text-muted-foreground tabular-nums">
                                                {index + 1}.
                                            </span>{' '}
                                            {step.title}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {step.text}
                                        </p>
                                    </div>
                                </li>
                            </Fragment>
                        ))}
                    </ol>
                </Card>

                {skills.length === 0 ? (
                    <Card className="flex flex-col items-center justify-center gap-3 border-dashed p-12 text-center">
                        <ClipboardCheck className="size-10 text-muted-foreground" />
                        <p className="font-medium">No stations or skills yet</p>
                        <p className="max-w-sm text-sm text-muted-foreground">
                            Add the first one, e.g. Making Station, Cleaning or
                            Communication, then give it a few questions.
                        </p>
                        {addSkillButton}
                    </Card>
                ) : (
                    <section className="flex flex-col gap-4">
                        <div className="flex flex-wrap items-end justify-between gap-3">
                            <h2 className="font-semibold tracking-tight">
                                Stations & skills
                            </h2>
                            <dl className="flex flex-wrap gap-2 text-sm">
                                <Stat label="Total" value={skills.length} />
                                <Stat
                                    label="Asked in assessments"
                                    value={askedCount}
                                />
                                <Stat label="Questions" value={questionCount} />
                            </dl>
                        </div>

                        {needQuestions.length > 0 && (
                            <p className="flex items-start gap-2 rounded-lg border border-amber-500/40 px-4 py-3 text-sm">
                                <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-500" />
                                <span>
                                    <span className="font-medium">
                                        {needQuestions
                                            .map((skill) => skill.name)
                                            .join(', ')}
                                    </span>{' '}
                                    {needQuestions.length === 1
                                        ? 'has no questions yet, so it isn’t asked'
                                        : 'have no questions yet, so they aren’t asked'}{' '}
                                    in assessments. Add at least one question.
                                </span>
                            </p>
                        )}

                        {skills.map((skill) => (
                            <SkillCard
                                key={skill.id}
                                skill={skill}
                                sectionOptions={sectionOptions}
                                answerTypeOptions={answerTypeOptions}
                            />
                        ))}
                    </section>
                )}
            </div>
        </>
    );
}

function Stat({ label, value }: { label: string; value: number }) {
    return (
        <div className="flex items-baseline gap-1.5 rounded-md border px-2.5 py-1">
            <dd className="font-semibold tabular-nums">{value}</dd>
            <dt className="text-xs text-muted-foreground">{label}</dt>
        </div>
    );
}

function StatusBadge({ skill }: { skill: AssessmentSkillSetup }) {
    if (!skill.is_active) {
        return (
            <Badge variant="secondary" className="gap-1">
                <CircleSlash className="size-3" /> Inactive · not asked
            </Badge>
        );
    }

    if (skill.questions.length === 0) {
        return (
            <Badge variant="outline" className="gap-1 text-muted-foreground">
                <AlertTriangle className="size-3 text-amber-500" /> Needs a
                question
            </Badge>
        );
    }

    return (
        <Badge variant="outline" className="gap-1 text-muted-foreground">
            <CheckCircle2 className="size-3 text-emerald-500" /> Asked in
            assessments
        </Badge>
    );
}

/**
 * On/off for whether this station/skill is asked in new assessments.
 * Clearer than a pair of buttons: one control, one state.
 */
function ActiveSwitch({
    skill,
    disabled,
    onChange,
}: {
    skill: AssessmentSkillSetup;
    disabled: boolean;
    onChange: (isActive: boolean) => void;
}) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={skill.is_active}
            aria-label={`Ask ${skill.name} in assessments`}
            title={
                skill.is_active
                    ? 'Turn off to stop asking this in new assessments'
                    : 'Turn on to ask this in new assessments'
            }
            disabled={disabled}
            onClick={() => onChange(!skill.is_active)}
            className="flex h-8 items-center gap-2 rounded-md px-2 text-sm font-medium transition-colors outline-none hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-60"
        >
            <span
                className={cn(
                    'flex h-5 w-9 shrink-0 items-center rounded-full p-0.5 transition-colors',
                    skill.is_active ? 'bg-primary' : 'bg-input',
                )}
            >
                <span
                    className={cn(
                        'size-4 rounded-full bg-white shadow-sm transition-transform',
                        skill.is_active ? 'translate-x-4' : 'translate-x-0',
                    )}
                />
            </span>
            <span
                className={cn(
                    'w-14 text-left',
                    !skill.is_active && 'text-muted-foreground',
                )}
            >
                {skill.is_active ? 'Active' : 'Inactive'}
            </span>
        </button>
    );
}

function SkillCard({
    skill,
    sectionOptions,
    answerTypeOptions,
}: {
    skill: AssessmentSkillSetup;
    sectionOptions: SectionOption[];
    answerTypeOptions: AnswerTypeOption[];
}) {
    const [saving, setSaving] = useState(false);
    const labelFor = (value: string) =>
        answerTypeOptions.find((option) => option.value === value)?.label ??
        value;
    const hasQuestions = skill.questions.length > 0;

    function setActive(isActive: boolean) {
        router.put(
            updateSkill(skill.id).url,
            {
                name: skill.name,
                description: skill.description,
                section_id: skill.section?.id ?? null,
                is_active: isActive,
            },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
            },
        );
    }

    return (
        <Card
            className={cn(
                'gap-0 overflow-hidden p-0',
                !skill.is_active && 'border-dashed shadow-none',
            )}
        >
            <header className="flex flex-col gap-3 p-5 sm:flex-row sm:items-start sm:justify-between">
                <div
                    className={cn(
                        'min-w-0 space-y-2',
                        !skill.is_active && 'opacity-70',
                    )}
                >
                    <div className="flex flex-wrap items-center gap-2">
                        <h3 className="text-lg font-semibold tracking-tight">
                            {skill.name}
                        </h3>
                        <StatusBadge skill={skill} />
                    </div>
                    {skill.description && (
                        <p className="max-w-3xl text-sm whitespace-pre-line text-muted-foreground">
                            {skill.description}
                        </p>
                    )}
                    {skill.section ? (
                        <p className="inline-flex items-center gap-1.5 rounded-md border px-2 py-1 text-xs">
                            <BookOpen className="size-3.5 text-muted-foreground" />
                            <span className="text-muted-foreground">
                                Suggests training:
                            </span>
                            <span className="font-medium">
                                {skill.section.title}
                            </span>
                        </p>
                    ) : (
                        <p className="inline-flex items-center gap-1.5 rounded-md border border-dashed px-2 py-1 text-xs text-muted-foreground">
                            <BookOpen className="size-3.5" />
                            No training linked — nothing is suggested for the
                            plan
                        </p>
                    )}
                </div>

                <div className="-mr-2 flex shrink-0 items-center gap-1">
                    <ActiveSwitch
                        skill={skill}
                        disabled={saving}
                        onChange={setActive}
                    />
                    <span aria-hidden className="mx-1 h-5 w-px bg-border" />
                    <SkillFormDialog
                        skill={skill}
                        sectionOptions={sectionOptions}
                        trigger={
                            <Button variant="ghost" size="sm">
                                <Pencil className="size-4" /> Edit
                            </Button>
                        }
                    />
                    <ConfirmDeleteDialog
                        title={`Remove ${skill.name}?`}
                        description="If anyone has already been assessed on it, it's deactivated instead so their ratings stay intact."
                        confirmLabel="Remove"
                        onConfirm={(close) =>
                            router.delete(destroySkill(skill.id).url, {
                                preserveScroll: true,
                                onSuccess: close,
                            })
                        }
                        trigger={
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-8 text-muted-foreground hover:text-destructive"
                                aria-label={`Remove ${skill.name}`}
                                title={`Remove ${skill.name}`}
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        }
                    />
                </div>
            </header>

            <div className="border-t border-border/60">
                <p className="px-5 pt-3 pb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    Questions{' '}
                    <span className="tabular-nums">
                        ({skill.questions.length})
                    </span>
                </p>

                {hasQuestions && (
                    <ol className="divide-y divide-border/60 border-y border-border/60">
                        {skill.questions.map((question, index) => {
                            const meta = ANSWER_TYPE_META[question.answer_type];

                            return (
                                <li
                                    key={question.id}
                                    className="flex flex-col gap-2 px-5 py-3 sm:flex-row sm:items-center sm:gap-4"
                                >
                                    <div className="flex min-w-0 flex-1 items-start gap-3">
                                        <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold text-muted-foreground tabular-nums">
                                            {index + 1}
                                        </span>
                                        <p className="min-w-0 pt-0.5 text-sm whitespace-pre-line">
                                            {question.prompt}
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-1 pl-9 sm:pl-0">
                                        <span
                                            className="mr-1 inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-medium text-muted-foreground"
                                            title={meta.hint}
                                        >
                                            <meta.icon className="size-3" />
                                            {labelFor(question.answer_type)}
                                        </span>
                                        <QuestionFormDialog
                                            skillId={skill.id}
                                            question={question}
                                            answerTypeOptions={
                                                answerTypeOptions
                                            }
                                            trigger={
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-8 text-muted-foreground hover:text-foreground"
                                                    aria-label="Edit question"
                                                    title="Edit question"
                                                >
                                                    <Pencil className="size-3.5" />
                                                </Button>
                                            }
                                        />
                                        <ConfirmDeleteDialog
                                            title="Remove this question?"
                                            description="It won't be asked in new assessments. Past assessments and ratings stay as they were."
                                            confirmLabel="Remove"
                                            onConfirm={(close) =>
                                                router.delete(
                                                    destroyQuestion(question.id)
                                                        .url,
                                                    {
                                                        preserveScroll: true,
                                                        onSuccess: close,
                                                    },
                                                )
                                            }
                                            trigger={
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-8 text-muted-foreground hover:text-destructive"
                                                    aria-label="Remove question"
                                                    title="Remove question"
                                                >
                                                    <Trash2 className="size-3.5" />
                                                </Button>
                                            }
                                        />
                                    </div>
                                </li>
                            );
                        })}
                    </ol>
                )}

                <div className="p-3">
                    <QuestionFormDialog
                        skillId={skill.id}
                        answerTypeOptions={answerTypeOptions}
                        trigger={
                            <button
                                type="button"
                                className={cn(
                                    'flex w-full items-center justify-center gap-2 rounded-lg border border-dashed px-4 py-2.5 text-sm font-medium text-muted-foreground transition-colors outline-none hover:border-primary/50 hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                    !hasQuestions &&
                                        skill.is_active &&
                                        'border-amber-500/50 text-foreground',
                                )}
                            >
                                <Plus className="size-4" />
                                {hasQuestions
                                    ? 'Add question'
                                    : 'Add the first question'}
                            </button>
                        }
                    />
                </div>
            </div>
        </Card>
    );
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Development Zone', href: developmentZoneIndex() },
    { title: 'Assessment setup', href: assessmentSetup() },
];

AssessmentSetup.layout = { breadcrumbs };
