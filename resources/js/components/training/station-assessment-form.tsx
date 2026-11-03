import { ClipboardCheck } from 'lucide-react';
import InputError from '@/components/input-error';
import { StarMeter } from '@/components/training/star-meter';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import type {
    AssessmentAnswerType,
    AssessmentSkillForm,
} from '@/types/training';

/** question id → raw answer (0/1 yes-no, 1–5 level, 0–100 percentage). */
export type AssessmentAnswers = Record<number, number>;

const LEVELS = [1, 2, 3, 4, 5];

/** Mirrors AssessmentAnswerType::normalize() on the server. */
function normalize(type: AssessmentAnswerType, value: number): number {
    switch (type) {
        case 'yes_no':
            return value >= 1 ? 1 : 0;
        case 'level':
            return Math.min(Math.max(value, 1), 5) / 5;
        case 'percentage':
            return Math.min(Math.max(value, 0), 100) / 100;
    }
}

/** A station/skill's live rating — null until every question is answered. */
export function skillStars(
    skill: AssessmentSkillForm,
    answers: AssessmentAnswers,
): number | null {
    if (skill.questions.some((q) => answers[q.id] === undefined)) {
        return null;
    }

    const shares = skill.questions.map((q) =>
        normalize(q.answer_type, answers[q.id]),
    );
    const average =
        shares.reduce((sum, share) => sum + share, 0) / shares.length;

    return Math.round(average * 5 * 4) / 4;
}

export function countUnanswered(
    skills: AssessmentSkillForm[],
    answers: AssessmentAnswers,
): number {
    return skills
        .flatMap((skill) => skill.questions)
        .filter((question) => answers[question.id] === undefined).length;
}

/**
 * Every station/skill's assessment questions with a simple answer control
 * each, and a live ¼-star rating per station/skill as it's filled in.
 */
export function StationAssessmentForm({
    skills,
    answers,
    onAnswer,
    errors,
    previousStars,
}: {
    skills: AssessmentSkillForm[];
    answers: AssessmentAnswers;
    onAnswer: (questionId: number, value: number | undefined) => void;
    errors: Partial<Record<string, string>>;
    /** skill id → rating from the last assessment, for comparison. */
    previousStars?: Record<number, number>;
}) {
    if (skills.length === 0) {
        return (
            <Card className="flex flex-col items-center gap-2 border-dashed p-8 text-center">
                <ClipboardCheck className="size-8 text-muted-foreground" />
                <p className="text-sm font-medium">
                    No assessment questions yet
                </p>
                <p className="max-w-sm text-sm text-muted-foreground">
                    The training team sets up stations & skills in the
                    Development Zone's Assessment setup. You can still submit
                    the overall evaluation.
                </p>
            </Card>
        );
    }

    return (
        <div className="flex flex-col gap-4">
            {skills.map((skill) => {
                const stars = skillStars(skill, answers);
                const previous = previousStars?.[skill.id];
                const answered = skill.questions.filter(
                    (q) => answers[q.id] !== undefined,
                ).length;

                return (
                    <Card key={skill.id} className="gap-0 p-0">
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border/60 p-4 sm:px-6">
                            <div>
                                <h3 className="font-semibold tracking-tight">
                                    {skill.name}
                                </h3>
                                {skill.description && (
                                    <p className="text-sm whitespace-pre-line text-muted-foreground">
                                        {skill.description}
                                    </p>
                                )}
                                <p className="text-xs text-muted-foreground tabular-nums">
                                    {answered} of {skill.questions.length}{' '}
                                    answered
                                </p>
                            </div>
                            <div className="flex items-center gap-4">
                                {previous !== undefined && (
                                    <div className="text-right">
                                        <p className="text-[11px] text-muted-foreground uppercase">
                                            Last time
                                        </p>
                                        <StarMeter value={previous} size="sm" />
                                    </div>
                                )}
                                <div className="text-right">
                                    <p className="text-[11px] text-muted-foreground uppercase">
                                        Rating
                                    </p>
                                    {stars === null ? (
                                        <p className="text-xs text-muted-foreground">
                                            Answer all to see
                                        </p>
                                    ) : (
                                        <StarMeter value={stars} showValue />
                                    )}
                                </div>
                            </div>
                        </div>
                        <ul className="divide-y divide-border/60">
                            {skill.questions.map((question) => {
                                const error = errors[`answers.${question.id}`];

                                return (
                                    <li
                                        key={question.id}
                                        data-invalid={error ? true : undefined}
                                        className={cn(
                                            'flex flex-col gap-3 p-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:gap-6',
                                            error && 'bg-destructive/5',
                                        )}
                                    >
                                        <div className="min-w-0 space-y-1">
                                            <p className="text-sm whitespace-pre-line">
                                                {question.prompt}
                                            </p>
                                            <InputError message={error} />
                                        </div>
                                        <div className="shrink-0">
                                            <AnswerControl
                                                id={`assessment-${question.id}`}
                                                type={question.answer_type}
                                                value={answers[question.id]}
                                                onChange={(value) =>
                                                    onAnswer(question.id, value)
                                                }
                                            />
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    </Card>
                );
            })}
        </div>
    );
}

function AnswerControl({
    id,
    type,
    value,
    onChange,
}: {
    id: string;
    type: AssessmentAnswerType;
    value: number | undefined;
    onChange: (value: number | undefined) => void;
}) {
    if (type === 'percentage') {
        return (
            <div className="relative w-32">
                <Input
                    id={id}
                    type="number"
                    inputMode="numeric"
                    min={0}
                    max={100}
                    step={1}
                    value={value ?? ''}
                    onChange={(event) => {
                        const raw = event.target.value;
                        onChange(
                            raw === ''
                                ? undefined
                                : Math.min(
                                      Math.max(Math.round(Number(raw)), 0),
                                      100,
                                  ),
                        );
                    }}
                    placeholder="0–100"
                    className="pr-8"
                    aria-label="Percentage"
                />
                <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-muted-foreground">
                    %
                </span>
            </div>
        );
    }

    if (type === 'level') {
        return (
            <div className="flex flex-col items-start gap-1 lg:items-end">
                <ToggleGroup
                    type="single"
                    variant="outline"
                    value={value === undefined ? '' : String(value)}
                    onValueChange={(next) =>
                        onChange(next ? Number(next) : undefined)
                    }
                    aria-label="Level from 1 to 5"
                >
                    {LEVELS.map((level) => (
                        <ToggleGroupItem
                            key={level}
                            value={String(level)}
                            className="w-10 font-semibold"
                        >
                            {level}
                        </ToggleGroupItem>
                    ))}
                </ToggleGroup>
                <span className="text-[11px] text-muted-foreground">
                    1 = just starting · 5 = fully capable
                </span>
            </div>
        );
    }

    return (
        <ToggleGroup
            type="single"
            variant="outline"
            value={value === undefined ? '' : String(value)}
            onValueChange={(next) => onChange(next ? Number(next) : undefined)}
            aria-label="Yes or no"
        >
            <ToggleGroupItem value="1" className="w-16">
                Yes
            </ToggleGroupItem>
            <ToggleGroupItem value="0" className="w-16">
                No
            </ToggleGroupItem>
        </ToggleGroup>
    );
}
