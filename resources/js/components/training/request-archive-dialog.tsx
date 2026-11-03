import { router, useForm } from '@inertiajs/react';
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
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { store } from '@/routes/trainees/archive-requests';

/**
 * A store manager's request to move a trainee to the Archive. The reason is
 * required — an admin reviews it before anything changes, and the trainee
 * stays on the active roster as "Archive pending" until then.
 */
export function RequestArchiveDialog({
    traineeId,
    traineeName,
    trigger,
}: {
    traineeId: number;
    traineeName: string;
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ reason: string }>({ reason: '' });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(store(traineeId).url, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
                // The Trainees list is reached through prefetching nav links —
                // flush so it shows the new "Archive pending" badge.
                router.flushAll();
            },
        });
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    form.clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Move {traineeName} to Archive?</DialogTitle>
                    <DialogDescription>
                        Your request goes to an admin for review. {traineeName}{' '}
                        stays on the active roster as pending until it's
                        approved.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="archive-reason">
                            Reason for archiving
                        </Label>
                        <Textarea
                            id="archive-reason"
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                            placeholder="e.g. Completed the trainee stage, or no longer continuing as a trainee because…"
                            rows={4}
                            maxLength={1000}
                            autoFocus
                        />
                        <InputError message={form.errors.reason} />
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
                                form.data.reason.trim().length === 0
                            }
                        >
                            Send for review
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
