import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Archive, Check, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { LocalTime } from '@/components/local-time';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import { show as traineeShow } from '@/routes/trainees';
import { approve, index, reject } from '@/routes/training/archive-requests';
import type { BreadcrumbItem } from '@/types';
import type { ArchiveRequestRow, ArchiveRequestStatus } from '@/types/training';

type Tab = 'pending' | 'history';

const STATUS_STYLES: Record<ArchiveRequestStatus, string> = {
    pending: 'text-amber-600 dark:text-amber-400',
    approved: 'text-emerald-600 dark:text-emerald-400',
    rejected: 'text-rose-600 dark:text-rose-400',
};

const STATUS_LABELS: Record<ArchiveRequestStatus, string> = {
    pending: 'Pending',
    approved: 'Approved',
    rejected: 'Rejected',
};

export default function ArchiveRequestsIndex() {
    const { requests, filters, pendingCount } = usePage<{
        requests: ArchiveRequestRow[];
        filters: { tab: Tab };
        pendingCount: number;
    }>().props;

    const [rejecting, setRejecting] = useState<ArchiveRequestRow | null>(null);
    const [approvingId, setApprovingId] = useState<number | null>(null);

    function switchTab(tab: Tab) {
        router.get(index().url, tab === 'history' ? { tab } : {}, {
            preserveScroll: true,
            replace: true,
        });
    }

    function approveRequest(request: ArchiveRequestRow) {
        setApprovingId(request.id);
        router.patch(
            approve(request.id).url,
            {},
            {
                preserveScroll: true,
                // The Trainees list and Dashboard counts change too.
                onSuccess: () => router.flushAll(),
                onFinish: () => setApprovingId(null),
            },
        );
    }

    return (
        <>
            <Head title="Archive Requests" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="Archive Requests"
                    description="Store managers ask to move trainees to the Archive. Approve to archive them, or reject to keep them active."
                />

                <ToggleGroup
                    type="single"
                    variant="outline"
                    value={filters.tab}
                    onValueChange={(tab) => tab && switchTab(tab as Tab)}
                    className="w-full justify-start sm:w-auto"
                >
                    <ToggleGroupItem value="pending">
                        Pending ({pendingCount})
                    </ToggleGroupItem>
                    <ToggleGroupItem value="history">History</ToggleGroupItem>
                </ToggleGroup>

                {requests.length === 0 ? (
                    <Card className="flex flex-col items-center justify-center gap-3 border-dashed p-12 text-center">
                        <Archive className="size-10 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            {filters.tab === 'pending'
                                ? 'No requests waiting for review.'
                                : 'No reviewed requests yet.'}
                        </p>
                    </Card>
                ) : (
                    <section className="surface-tray">
                        <ul className="surface-core divide-y divide-border/60 overflow-hidden">
                            {requests.map((request) => (
                                <li
                                    key={request.id}
                                    className="flex flex-col gap-3 p-4 lg:flex-row lg:items-start lg:gap-6"
                                >
                                    <div className="min-w-0 space-y-1.5 lg:flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Link
                                                href={
                                                    traineeShow(
                                                        request.trainee.id,
                                                    ).url
                                                }
                                                className="font-medium hover:underline"
                                            >
                                                {request.trainee.name}
                                            </Link>
                                            <Badge
                                                variant="outline"
                                                className={cn(
                                                    STATUS_STYLES[
                                                        request.status
                                                    ],
                                                )}
                                            >
                                                {STATUS_LABELS[request.status]}
                                            </Badge>
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            {[
                                                request.trainee.position,
                                                request.store.name,
                                                request.requested_by
                                                    ? `Requested by ${request.requested_by.name}`
                                                    : null,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}{' '}
                                            ·{' '}
                                            <LocalTime
                                                iso={request.created_at}
                                                variant="relative"
                                            />
                                        </p>
                                        <p className="text-sm whitespace-pre-line">
                                            {request.reason}
                                        </p>
                                        {request.status !== 'pending' && (
                                            <p className="text-xs text-muted-foreground">
                                                {STATUS_LABELS[request.status]}
                                                {request.reviewed_by
                                                    ? ` by ${request.reviewed_by.name}`
                                                    : ''}
                                                {request.reviewed_at && (
                                                    <>
                                                        {' · '}
                                                        <LocalTime
                                                            iso={
                                                                request.reviewed_at
                                                            }
                                                            variant="datetime"
                                                        />
                                                    </>
                                                )}
                                                {request.review_note
                                                    ? ` — “${request.review_note}”`
                                                    : ''}
                                            </p>
                                        )}
                                    </div>

                                    {request.status === 'pending' && (
                                        <div className="flex shrink-0 gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setRejecting(request)
                                                }
                                            >
                                                <X className="size-4" /> Reject
                                            </Button>
                                            <Button
                                                size="sm"
                                                disabled={
                                                    approvingId === request.id
                                                }
                                                onClick={() =>
                                                    approveRequest(request)
                                                }
                                            >
                                                <Check className="size-4" />{' '}
                                                Approve &amp; archive
                                            </Button>
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>

            <RejectDialog
                request={rejecting}
                onClose={() => setRejecting(null)}
            />
        </>
    );
}

function RejectDialog({
    request,
    onClose,
}: {
    request: ArchiveRequestRow | null;
    onClose: () => void;
}) {
    const form = useForm<{ review_note: string }>({ review_note: '' });

    function submit(event: FormEvent) {
        event.preventDefault();

        if (!request) {
            return;
        }

        form.patch(reject(request.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                router.flushAll();
                onClose();
            },
        });
    }

    return (
        <Dialog
            open={request !== null}
            onOpenChange={(open) => {
                if (!open) {
                    form.reset();
                    form.clearErrors();
                    onClose();
                }
            }}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Reject archive request?</DialogTitle>
                    <DialogDescription>
                        {request?.trainee.name} stays on the active roster. The
                        store manager sees your note on the trainee's page.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="review-note">Note (optional)</Label>
                        <Textarea
                            id="review-note"
                            value={form.data.review_note}
                            onChange={(event) =>
                                form.setData('review_note', event.target.value)
                            }
                            placeholder="e.g. Needs two more weeks on the Making station first."
                            rows={3}
                            maxLength={1000}
                        />
                        <InputError message={form.errors.review_note} />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={form.processing}
                        >
                            Reject request
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Archive Requests', href: index() },
];

ArchiveRequestsIndex.layout = { breadcrumbs };
