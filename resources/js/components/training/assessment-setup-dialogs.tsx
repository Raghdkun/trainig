import { useForm } from '@inertiajs/react';
import { Percent, SignalHigh, ToggleRight } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import {
    store as storeQuestion,
    update as updateQuestion,
} from '@/routes/training/assessment-questions';
import {
    store as storeSkill,
    update as updateSkill,
} from '@/routes/training/assessment-skills';
import type {
    AssessmentAnswerType,
    AssessmentQuestion,
    AssessmentSkillSetup,
} from '@/types/training';

export type AnswerTypeOption = { value: AssessmentAnswerType; label: string };
export type SectionOption = { id: number; title: string };

const NO_SECTION = 'none';

/**
 * How each answer type looks and scores, shared by the question dialog and
 * the setup page so the two always describe it the same way.
 */
export const ANSWER_TYPE_META: Record<
    AssessmentAnswerType,
    { icon: LucideIcon; hint: string }
> = {
    yes_no: {
        icon: ToggleRight,
        hint: 'Yes counts as 100%, No as 0%.',
    },
    level: {
        icon: SignalHigh,
        hint: 'Rated 1–5, where 5 means fully capable.',
    },
    percentage: {
        icon: Percent,
        hint: 'A score from 0 to 100%.',
    },
};

/** A small mock of the control the evaluator will see for this answer type. */
function AnswerPreview({ type }: { type: AssessmentAnswerType }) {
    const chip =
        'flex h-6 items-center justify-center rounded border bg-background px-1.5 text-[11px] font-medium text-muted-foreground';

    if (type === 'yes_no') {
        return (
            <span className="flex gap-1" aria-hidden>
                <span className={chip}>Yes</span>
                <span className={chip}>No</span>
            </span>
        );
    }

    if (type === 'level') {
        return (
            <span className="flex gap-1" aria-hidden>
                {[1, 2, 3, 4, 5].map((level) => (
                    <span key={level} className={cn(chip, 'w-6 px-0')}>
                        {level}
                    </span>
                ))}
            </span>
        );
    }

    return (
        <span className={cn(chip, 'w-20 justify-between')} aria-hidden>
            0–100 <span>%</span>
        </span>
    );
}

/** Add or edit a station/skill: name, description and optional content link. */
export function SkillFormDialog({
    skill,
    sectionOptions,
    trigger,
}: {
    skill?: AssessmentSkillSetup;
    sectionOptions: SectionOption[];
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const initial = () => ({
        name: skill?.name ?? '',
        description: skill?.description ?? '',
        section_id: skill?.section ? String(skill.section.id) : NO_SECTION,
    });
    const form = useForm(initial());

    function onOpenChange(next: boolean) {
        if (next) {
            form.setData(initial());
            form.clearErrors();
        }

        setOpen(next);
    }

    function submit(event: FormEvent) {
        event.preventDefault();

        form.transform((data) => ({
            name: data.name,
            description:
                data.description.trim() === '' ? null : data.description,
            section_id:
                data.section_id === NO_SECTION ? null : Number(data.section_id),
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };

        if (skill) {
            form.put(updateSkill(skill.id).url, options);
        } else {
            form.post(storeSkill().url, options);
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {skill
                            ? 'Edit station / skill'
                            : 'New station or skill'}
                    </DialogTitle>
                    <DialogDescription>
                        Anything the employee should be rated on — a station
                        like Making, or a skill like Cleaning or Communication.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-5">
                    <div className="grid gap-2">
                        <Label htmlFor="skill-name">
                            Name
                            <span aria-hidden className="ml-0.5 text-red-500">
                                *
                            </span>
                        </Label>
                        <Input
                            id="skill-name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            placeholder="e.g. Making Station, Cleaning, Communication"
                            maxLength={255}
                            autoFocus
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="skill-description">
                            Description{' '}
                            <span className="font-normal text-muted-foreground">
                                (optional)
                            </span>
                        </Label>
                        <Textarea
                            id="skill-description"
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                            placeholder="Guidance shown to whoever is rating it."
                            rows={3}
                            maxLength={1000}
                        />
                        <InputError message={form.errors.description} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="skill-section">
                            Training content{' '}
                            <span className="font-normal text-muted-foreground">
                                (optional)
                            </span>
                        </Label>
                        <Select
                            value={form.data.section_id}
                            onValueChange={(value) =>
                                form.setData('section_id', value)
                            }
                        >
                            <SelectTrigger
                                id="skill-section"
                                className="w-full"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NO_SECTION}>
                                    No training linked
                                </SelectItem>
                                {sectionOptions.map((section) => (
                                    <SelectItem
                                        key={section.id}
                                        value={String(section.id)}
                                    >
                                        {section.title}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <p className="text-xs text-muted-foreground">
                            When this is a development need, that station's
                            training content is suggested for the plan.
                        </p>
                        <InputError message={form.errors.section_id} />
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
                            disabled={
                                form.processing || form.data.name.trim() === ''
                            }
                        >
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/** Add or edit one question for a station/skill. */
export function QuestionFormDialog({
    skillId,
    question,
    answerTypeOptions,
    trigger,
}: {
    skillId: number;
    question?: AssessmentQuestion;
    answerTypeOptions: AnswerTypeOption[];
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const initial = () => ({
        prompt: question?.prompt ?? '',
        answer_type: (question?.answer_type ??
            'yes_no') as AssessmentAnswerType,
    });
    const form = useForm(initial());

    function onOpenChange(next: boolean) {
        if (next) {
            form.setData(initial());
            form.clearErrors();
        }

        setOpen(next);
    }

    function submit(event: FormEvent) {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };

        if (question) {
            form.put(updateQuestion(question.id).url, options);
        } else {
            form.post(storeQuestion(skillId).url, options);
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {question ? 'Edit question' : 'New question'}
                    </DialogTitle>
                    <DialogDescription>
                        What should the evaluator check about the employee?
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-5">
                    <div className="grid gap-2">
                        <Label htmlFor="assessment-prompt">
                            Question
                            <span aria-hidden className="ml-0.5 text-red-500">
                                *
                            </span>
                        </Label>
                        <Textarea
                            id="assessment-prompt"
                            value={form.data.prompt}
                            onChange={(e) =>
                                form.setData('prompt', e.target.value)
                            }
                            placeholder="e.g. Can they stretch a large dough to size without tearing it?"
                            rows={3}
                            maxLength={500}
                            autoFocus
                        />
                        <InputError message={form.errors.prompt} />
                    </div>
                    <div className="grid gap-2">
                        <Label id="assessment-answer-type">
                            How is it answered?
                        </Label>
                        <div
                            role="radiogroup"
                            aria-labelledby="assessment-answer-type"
                            className="grid gap-2"
                        >
                            {answerTypeOptions.map((option) => {
                                const meta = ANSWER_TYPE_META[option.value];
                                const Icon = meta.icon;
                                const selected =
                                    form.data.answer_type === option.value;

                                return (
                                    <button
                                        key={option.value}
                                        type="button"
                                        role="radio"
                                        aria-checked={selected}
                                        onClick={() =>
                                            form.setData(
                                                'answer_type',
                                                option.value,
                                            )
                                        }
                                        className={cn(
                                            'flex items-center gap-3 rounded-lg border p-3 text-left transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                            selected
                                                ? 'border-primary'
                                                : 'hover:border-foreground/30',
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'flex size-8 shrink-0 items-center justify-center rounded-md border',
                                                selected
                                                    ? 'border-primary/60 text-primary'
                                                    : 'text-muted-foreground',
                                            )}
                                        >
                                            <Icon className="size-4" />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block text-sm font-medium">
                                                {option.label}
                                            </span>
                                            <span className="block text-xs text-muted-foreground">
                                                {meta.hint}
                                            </span>
                                        </span>
                                        <span className="hidden sm:block">
                                            <AnswerPreview
                                                type={option.value}
                                            />
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                        <InputError message={form.errors.answer_type} />
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
                            disabled={
                                form.processing ||
                                form.data.prompt.trim() === ''
                            }
                        >
                            Save question
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
