import { useState } from 'react';
import type { ReactNode } from 'react';
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

/**
 * Reusable destructive-confirmation dialog. The parent supplies the delete
 * action via `onConfirm`; this component only owns the open/closed state.
 */
export function ConfirmDeleteDialog({
    trigger,
    title,
    description,
    onConfirm,
    processing = false,
    confirmLabel = 'Delete',
}: {
    trigger: ReactNode;
    title: string;
    description: string;
    onConfirm: (close: () => void) => void;
    processing?: boolean;
    confirmLabel?: string;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        disabled={processing}
                        onClick={() => onConfirm(() => setOpen(false))}
                    >
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
