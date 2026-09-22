import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { StarRating } from '@/components/training/star-rating';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { store } from '@/routes/trainees/development';
import type { AddableTrainee, DevelopmentCriterion } from '@/types/training';

export function AddToDevelopmentZoneDialog({
    trainees,
    criteria,
    trigger,
}: {
    trainees: AddableTrainee[];
    criteria: DevelopmentCriterion[];
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const [traineeId, setTraineeId] = useState<number | null>(null);
    const form = useForm<{
        notes: string;
        ratings: { criterion_id: number; rating: number }[];
    }>({ notes: '', ratings: [] });

    function onOpenChange(next: boolean) {
        if (next) {
            setTraineeId(trainees[0]?.id ?? null);
            form.setData({ notes: '', ratings: [] });
        }

        setOpen(next);
    }

    function ratingFor(criterionId: number): number | null {
        return (
            form.data.ratings.find((r) => r.criterion_id === criterionId)
                ?.rating ?? null
        );
    }

    function setRating(criterionId: number, rating: number) {
        const next = form.data.ratings.filter(
            (r) => r.criterion_id !== criterionId,
        );
        form.setData('ratings', [
            ...next,
            { criterion_id: criterionId, rating },
        ]);
    }

    function submit(event: FormEvent) {
        event.preventDefault();

        if (!traineeId) {
            return;
        }

        form.post(store(traineeId).url, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                router.flushAll();
            },
        });
    }

    const allRated =
        criteria.length > 0 && criteria.every((c) => ratingFor(c.id) !== null);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="flex max-h-[85vh] flex-col sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Add to Development Zone</DialogTitle>
                    <DialogDescription>
                        Evaluate this trainee's current performance. An admin
                        will review it and build their development plan.
                    </DialogDescription>
                </DialogHeader>
                <form
                    onSubmit={submit}
                    className="flex min-h-0 flex-1 flex-col gap-4"
                >
                    <div className="grid shrink-0 gap-2">
                        <Label htmlFor="dev-zone-trainee">Trainee</Label>
                        <Select
                            value={traineeId ? String(traineeId) : undefined}
                            onValueChange={(value) =>
                                setTraineeId(Number(value))
                            }
                        >
                            <SelectTrigger
                                id="dev-zone-trainee"
                                className="w-full"
                            >
                                <SelectValue placeholder="Choose a trainee" />
                            </SelectTrigger>
                            <SelectContent>
                                {trainees.map((trainee) => (
                                    <SelectItem
                                        key={trainee.id}
                                        value={String(trainee.id)}
                                    >
                                        {trainee.name}
                                        {trainee.position
                                            ? ` · ${trainee.position}`
                                            : ''}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid min-h-0 flex-1 auto-rows-min gap-2.5 overflow-y-auto pr-1 sm:grid-cols-2">
                        {criteria.map((criterion) => (
                            <div
                                key={criterion.id}
                                className="flex items-center justify-between gap-3 rounded-lg border p-3"
                            >
                                <div className="min-w-0">
                                    <p className="text-sm font-medium">
                                        {criterion.label}
                                    </p>
                                    {criterion.description && (
                                        <p className="truncate text-xs text-muted-foreground">
                                            {criterion.description}
                                        </p>
                                    )}
                                </div>
                                <StarRating
                                    value={ratingFor(criterion.id)}
                                    onChange={(value) =>
                                        setRating(criterion.id, value)
                                    }
                                />
                            </div>
                        ))}
                    </div>

                    <div className="grid shrink-0 gap-2">
                        <Label htmlFor="dev-zone-notes">Notes</Label>
                        <Textarea
                            id="dev-zone-notes"
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
                            }
                            placeholder="What should the admin know when building this plan?"
                        />
                    </div>

                    <DialogFooter className="shrink-0">
                        <Button
                            type="submit"
                            disabled={
                                form.processing || !traineeId || !allRated
                            }
                        >
                            Submit evaluation
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
