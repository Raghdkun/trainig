import { router } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { useState } from 'react';
import { ImportanceBadge } from '@/components/training/importance-badge';
import { MediaAttachments } from '@/components/training/media-attachments';
import { RatingInput } from '@/components/training/rating-input';
import { Checkbox } from '@/components/ui/checkbox';
import { Textarea } from '@/components/ui/textarea';
import { useCurrentStep } from '@/hooks/use-current-step';
import { cn } from '@/lib/utils';
import { update as updateEvaluation } from '@/routes/trainees/evaluations';
import type { EvaluationItem as EvaluationItemType } from '@/types/training';

function persist(
    traineeId: number,
    itemId: number,
    data: { completed: boolean; rating: number | null; notes: string | null },
) {
    router.put(updateEvaluation([traineeId, itemId]).url, data, {
        preserveScroll: true,
        preserveState: true,
        only: ['progress'],
    });
}

export function EvaluationItem({
    item,
    traineeId,
    currentStepId,
    isSub = false,
}: {
    item: EvaluationItemType;
    traineeId: number;
    currentStepId: number | null;
    isSub?: boolean;
}) {
    // Only leaf items are checkable; a parent reflects its children.
    if (item.children.length > 0) {
        return (
            <EvaluationParent
                item={item}
                traineeId={traineeId}
                currentStepId={currentStepId}
                isSub={isSub}
            />
        );
    }

    return (
        <EvaluationLeaf
            item={item}
            traineeId={traineeId}
            currentStepId={currentStepId}
            isSub={isSub}
        />
    );
}

function EvaluationParent({
    item,
    traineeId,
    currentStepId,
    isSub,
}: {
    item: EvaluationItemType;
    traineeId: number;
    currentStepId: number | null;
    isSub: boolean;
}) {
    const completed = item.evaluation?.completed ?? false;

    return (
        <div
            className={cn(
                'rounded-lg border bg-muted/20 p-3',
                isSub && 'border-dashed',
            )}
        >
            <div className="flex flex-wrap items-center gap-2">
                <span className="text-sm font-semibold">{item.title}</span>
                <ImportanceBadge importance={item.importance} />
                {completed && (
                    <span className="text-xs font-medium text-emerald-600 dark:text-emerald-400">
                        Complete
                    </span>
                )}
            </div>

            {item.content && (
                <p className="mt-2 rounded bg-muted/50 px-2 py-1.5 text-sm whitespace-pre-line text-muted-foreground">
                    {item.content}
                </p>
            )}

            {item.media.length > 0 && (
                <div className="mt-2">
                    <MediaAttachments media={item.media} />
                </div>
            )}

            <div className="mt-3 space-y-2 border-t pt-3 pl-4">
                {item.children.map((child) => (
                    <EvaluationItem
                        key={child.id}
                        item={child}
                        traineeId={traineeId}
                        currentStepId={currentStepId}
                        isSub
                    />
                ))}
            </div>
        </div>
    );
}

function EvaluationLeaf({
    item,
    traineeId,
    currentStepId,
    isSub,
}: {
    item: EvaluationItemType;
    traineeId: number;
    currentStepId: number | null;
    isSub: boolean;
}) {
    const ev = item.evaluation;

    const [completed, setCompleted] = useState(ev?.completed ?? false);
    const [rating, setRating] = useState<number | null>(ev?.rating ?? null);
    const [notes, setNotes] = useState(ev?.notes ?? '');

    // Re-sync local state when the server sends new data.
    const signature = `${ev?.completed ? 1 : 0}|${ev?.rating ?? ''}|${ev?.notes ?? ''}`;
    const [knownSignature, setKnownSignature] = useState(signature);

    if (signature !== knownSignature) {
        setKnownSignature(signature);
        setCompleted(ev?.completed ?? false);
        setRating(ev?.rating ?? null);
        setNotes(ev?.notes ?? '');
    }

    const isCurrent = item.id === currentStepId;
    const stepRef = useCurrentStep(isCurrent);

    // A not-rated item is just "done / not done" — no score or note gate.
    const scored = item.requires_rating;
    const canComplete = !scored || (rating !== null && notes.trim() !== '');

    function save(
        nextCompleted: boolean,
        nextRating: number | null,
        nextNotes: string,
    ) {
        // Invariant: a scored item can only complete with a score and a note; a
        // not-rated item never carries a score.
        const valid = !scored || (nextRating !== null && nextNotes.trim() !== '');
        const finalCompleted = nextCompleted && valid;
        const finalRating = scored ? nextRating : null;

        setCompleted(finalCompleted);
        setRating(finalRating);
        setNotes(nextNotes);

        persist(traineeId, item.id, {
            completed: finalCompleted,
            rating: finalRating,
            notes: nextNotes.trim() === '' ? null : nextNotes,
        });
    }

    function toggle(next: boolean) {
        save(next, rating, notes);
    }

    function rate(next: number | null) {
        // Keep `completed` unless clearing the score would break the invariant.
        save(completed, next, notes);
    }

    function commitNotes() {
        if ((ev?.notes ?? '') !== notes) {
            save(completed, rating, notes);
        }
    }

    return (
        <div
            ref={stepRef}
            className={cn(
                'rounded-lg border p-3 transition-colors',
                completed
                    ? 'border-emerald-200 bg-emerald-50/40 dark:border-emerald-900 dark:bg-emerald-950/20'
                    : 'bg-background',
                isCurrent &&
                    'ring-2 ring-primary ring-offset-2 ring-offset-background',
                isSub && 'border-dashed',
            )}
        >
            <div className="flex items-start gap-3">
                <Checkbox
                    checked={completed}
                    disabled={!completed && !canComplete}
                    onCheckedChange={(value) => toggle(value === true)}
                    className="mt-0.5"
                />

                <div className="min-w-0 flex-1 space-y-2">
                    <div className="flex flex-wrap items-center gap-2">
                        <span
                            className={cn(
                                'text-sm font-medium',
                                completed &&
                                    'text-muted-foreground line-through',
                            )}
                        >
                            {item.title}
                        </span>
                        <ImportanceBadge importance={item.importance} />
                        {isCurrent && (
                            <span className="rounded-full bg-primary px-2 py-0.5 text-xs font-medium text-primary-foreground">
                                Current step
                            </span>
                        )}
                    </div>

                    {item.content && (
                        <p className="rounded bg-muted/50 px-2 py-1.5 text-sm whitespace-pre-line text-muted-foreground">
                            {item.content}
                        </p>
                    )}

                    <MediaAttachments media={item.media} />

                    {scored && <RatingInput value={rating} onChange={rate} />}

                    <Textarea
                        value={notes}
                        onChange={(e) => setNotes(e.target.value)}
                        onBlur={commitNotes}
                        placeholder={scored ? 'Add a note…' : 'Add a note (optional)…'}
                        className="min-h-0 resize-none py-1.5 text-sm"
                        rows={2}
                    />

                    {scored && !completed && !canComplete && (
                        <p className="flex items-center gap-1 text-xs text-muted-foreground">
                            <Lock className="size-3" />
                            Add a score and a note to mark this step complete.
                        </p>
                    )}
                </div>
            </div>
        </div>
    );
}
