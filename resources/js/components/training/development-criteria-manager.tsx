import { router, useForm } from '@inertiajs/react';
import { Check, Pencil, Plus, Settings2, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { ConfirmDeleteDialog } from '@/components/training/confirm-delete-dialog';
import { Badge } from '@/components/ui/badge';
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
import { destroy, store, update } from '@/routes/admin/development-criteria';
import type { DevelopmentCriterion } from '@/types/training';

function AddCriterionForm({ onAdded }: { onAdded: () => void }) {
    const form = useForm({ label: '', description: '' });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(store().url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onAdded();
            },
        });
    }

    return (
        <form
            onSubmit={submit}
            className="space-y-2 rounded-lg border bg-muted/20 p-3"
        >
            <Label
                htmlFor="criterion-label"
                className="text-xs tracking-wide text-muted-foreground uppercase"
            >
                Add a question
            </Label>
            <div className="flex items-start gap-2">
                <div className="flex-1">
                    <Input
                        id="criterion-label"
                        value={form.data.label}
                        onChange={(e) => form.setData('label', e.target.value)}
                        placeholder="e.g. Communication"
                        required
                    />
                    <InputError message={form.errors.label} />
                </div>
                <Button type="submit" disabled={form.processing}>
                    <Plus className="size-4" /> Add
                </Button>
            </div>
        </form>
    );
}

function EditCriterionForm({
    criterion,
    onDone,
}: {
    criterion: DevelopmentCriterion;
    onDone: () => void;
}) {
    const form = useForm({
        label: criterion.label,
        description: criterion.description ?? '',
        is_active: criterion.is_active ?? true,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.patch(update(criterion.id).url, {
            preserveScroll: true,
            onSuccess: onDone,
        });
    }

    return (
        <form
            onSubmit={submit}
            className="space-y-2 rounded-lg border border-primary/40 bg-muted/20 p-3"
        >
            <div>
                <Label
                    htmlFor={`criterion-${criterion.id}-label`}
                    className="text-xs text-muted-foreground"
                >
                    Question
                </Label>
                <Input
                    id={`criterion-${criterion.id}-label`}
                    value={form.data.label}
                    onChange={(e) => form.setData('label', e.target.value)}
                    autoFocus
                    required
                />
                <InputError message={form.errors.label} />
            </div>
            <div>
                <Label
                    htmlFor={`criterion-${criterion.id}-description`}
                    className="text-xs text-muted-foreground"
                >
                    Description (optional)
                </Label>
                <Input
                    id={`criterion-${criterion.id}-description`}
                    value={form.data.description}
                    onChange={(e) =>
                        form.setData('description', e.target.value)
                    }
                    placeholder="Shown to the manager under the question"
                />
                <InputError message={form.errors.description} />
            </div>
            <div className="flex justify-end gap-1.5">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onDone}
                >
                    <X className="size-4" /> Cancel
                </Button>
                <Button type="submit" size="sm" disabled={form.processing}>
                    <Check className="size-4" /> Save
                </Button>
            </div>
        </form>
    );
}

function CriterionRow({ criterion }: { criterion: DevelopmentCriterion }) {
    const [isEditing, setIsEditing] = useState(false);

    function toggleActive() {
        router.patch(
            update(criterion.id).url,
            {
                label: criterion.label,
                description: criterion.description,
                is_active: !criterion.is_active,
            },
            { preserveScroll: true },
        );
    }

    if (isEditing) {
        return (
            <EditCriterionForm
                criterion={criterion}
                onDone={() => setIsEditing(false)}
            />
        );
    }

    return (
        <div className="flex items-center justify-between gap-3 rounded-lg border p-3 transition-colors hover:bg-muted/30">
            <div className="min-w-0">
                <div className="flex items-center gap-2">
                    <p className="truncate text-sm font-medium">
                        {criterion.label}
                    </p>
                    {!criterion.is_active && (
                        <Badge variant="secondary">Inactive</Badge>
                    )}
                </div>
                {criterion.description && (
                    <p className="truncate text-xs text-muted-foreground">
                        {criterion.description}
                    </p>
                )}
            </div>
            <div className="flex shrink-0 items-center gap-1.5">
                <Button
                    variant="ghost"
                    size="icon"
                    className="text-muted-foreground hover:text-foreground"
                    onClick={() => setIsEditing(true)}
                >
                    <Pencil className="size-4" />
                </Button>
                <Button variant="outline" size="sm" onClick={toggleActive}>
                    {criterion.is_active ? 'Deactivate' : 'Activate'}
                </Button>
                <ConfirmDeleteDialog
                    title="Delete this question?"
                    description="This removes it permanently. If it's already been used in an evaluation, deactivate it instead."
                    onConfirm={(close) =>
                        router.delete(destroy(criterion.id).url, {
                            preserveScroll: true,
                            onSuccess: close,
                        })
                    }
                    trigger={
                        <Button
                            variant="ghost"
                            size="icon"
                            className="text-muted-foreground hover:text-destructive"
                        >
                            <Trash2 className="size-4" />
                        </Button>
                    }
                />
            </div>
        </div>
    );
}

/**
 * Admin-only: manage the catalog of evaluation questions Managers rate when
 * adding a trainee to the Development Zone. Deactivating a question (rather
 * than deleting it) keeps past evaluations that used it intact; deleting is
 * only allowed for questions no evaluation has used yet.
 */
export function DevelopmentCriteriaManager({
    criteria,
    trigger,
}: {
    criteria: DevelopmentCriterion[];
    trigger?: ReactNode;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {trigger ?? (
                    <Button variant="outline" size="sm">
                        <Settings2 className="size-4" /> Manage questions
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="flex max-h-[85vh] flex-col sm:max-w-2xl">
                <DialogHeader className="shrink-0">
                    <DialogTitle>Evaluation questions</DialogTitle>
                    <DialogDescription>
                        These are the questions Managers rate when adding a
                        trainee to the Development Zone.
                    </DialogDescription>
                </DialogHeader>

                <div className="min-h-0 flex-1 space-y-2 overflow-y-auto pr-1">
                    {criteria.map((criterion) => (
                        <CriterionRow
                            key={criterion.id}
                            criterion={criterion}
                        />
                    ))}
                </div>

                <div className="shrink-0">
                    <AddCriterionForm onAdded={() => router.reload()} />
                </div>

                <DialogFooter className="shrink-0">
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
