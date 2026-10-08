import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    CircleDot,
    ClipboardCheck,
    Clock,
    RotateCcw,
    Trash2,
    User,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { ConfirmDeleteDialog } from '@/components/training/confirm-delete-dialog';
import { DevelopmentPlanPanel } from '@/components/training/development-plan-panel';
import { DevelopmentStatusBadge } from '@/components/training/development-status-badge';
import { StarRating } from '@/components/training/star-rating';
import { SkillRatingsCard } from '@/components/training/station-ratings-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { useInitials } from '@/hooks/use-initials';
import {
    index as developmentZoneIndex,
    reassess,
    show as developmentZoneShow,
} from '@/routes/development-zone';
import { show as traineeShow } from '@/routes/trainees';
import { complete, destroy, reopen } from '@/routes/trainees/development';
import type { BreadcrumbItem } from '@/types';
import type {
    DevelopmentStatus,
    DevelopmentZoneShowData,
} from '@/types/training';

type StatusAction = 'complete' | 'reopen' | 'remove';

export default function DevelopmentZoneShow() {
    const {
        trainee,
        evaluation,
        skillRatings,
        assessmentHistory,
        developmentPlan,
        developmentPicker,
        canManagePlan,
        canComplete,
        canRemove,
        canReassess,
    } = usePage<DevelopmentZoneShowData>().props;
    const getInitials = useInitials();
    const [busy, setBusy] = useState<StatusAction | null>(null);

    function changeStatus(action: 'complete' | 'reopen') {
        const route = action === 'complete' ? complete : reopen;

        router.patch(
            route(trainee.id).url,
            {},
            {
                preserveScroll: true,
                onStart: () => setBusy(action),
                onFinish: () => setBusy(null),
                onSuccess: () => router.flushAll(),
            },
        );
    }

    function removeFromZone(close: () => void) {
        router.delete(destroy(trainee.id).url, {
            preserveScroll: true,
            onStart: () => setBusy('remove'),
            onFinish: () => setBusy(null),
            onSuccess: () => {
                close();
                router.flushAll();
                router.visit(developmentZoneIndex().url);
            },
        });
    }

    return (
        <>
            <Head title={`${trainee.name} · Development Zone`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <Link
                    href={developmentZoneIndex().url}
                    className="flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" /> Development Zone
                </Link>

                <Card className="gap-0 overflow-hidden p-0">
                    <div className="flex flex-wrap items-start justify-between gap-4 p-5">
                        <div className="flex min-w-0 items-center gap-4">
                            <span
                                className="flex size-12 shrink-0 items-center justify-center rounded-full bg-muted text-base font-semibold text-muted-foreground"
                                aria-hidden
                            >
                                {getInitials(trainee.name)}
                            </span>
                            <div className="min-w-0 space-y-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h1 className="text-xl font-semibold tracking-tight">
                                        {trainee.name}
                                    </h1>
                                    <DevelopmentStatusBadge
                                        status={trainee.status}
                                    />
                                    {trainee.development_only && (
                                        <Badge variant="secondary">
                                            New employee
                                        </Badge>
                                    )}
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {[
                                        trainee.position,
                                        trainee.store.name,
                                        trainee.hired_at
                                            ? `Hired ${new Date(
                                                  `${trainee.hired_at}T00:00:00`,
                                              ).toLocaleDateString(undefined, {
                                                  year: 'numeric',
                                                  month: 'short',
                                                  day: 'numeric',
                                              })}`
                                            : null,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </p>
                            </div>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            {!trainee.development_only && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={traineeShow(trainee.id).url}>
                                        <User className="size-4" /> View profile
                                    </Link>
                                </Button>
                            )}
                            {canReassess && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={reassess(trainee.id).url}>
                                        <ClipboardCheck className="size-4" />{' '}
                                        Reassess
                                    </Link>
                                </Button>
                            )}
                            {canComplete && trainee.status === 'active' && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={busy !== null}
                                    onClick={() => changeStatus('complete')}
                                >
                                    {busy === 'complete' ? (
                                        <Spinner />
                                    ) : (
                                        <CheckCircle2 className="size-4" />
                                    )}
                                    Mark completed
                                </Button>
                            )}
                            {canComplete && trainee.status === 'completed' && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={busy !== null}
                                    onClick={() => changeStatus('reopen')}
                                >
                                    {busy === 'reopen' ? (
                                        <Spinner />
                                    ) : (
                                        <RotateCcw className="size-4" />
                                    )}
                                    Reopen
                                </Button>
                            )}
                            {canRemove && (
                                <ConfirmDeleteDialog
                                    title={`Remove ${trainee.name} from the Development Zone?`}
                                    description="Their evaluation and plan history are kept (just hidden) in case they’re added again."
                                    confirmLabel="Remove"
                                    processing={busy === 'remove'}
                                    onConfirm={removeFromZone}
                                    trigger={
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="text-muted-foreground hover:text-destructive"
                                        >
                                            <Trash2 className="size-4" /> Remove
                                        </Button>
                                    }
                                />
                            )}
                        </div>
                    </div>

                    <StatusNote
                        status={trainee.status}
                        canManage={canComplete}
                    />
                </Card>

                <SkillRatingsCard
                    ratings={skillRatings}
                    history={assessmentHistory}
                />

                <Card className="gap-4 p-5">
                    <h2 className="font-semibold tracking-tight">
                        Manager evaluation
                    </h2>
                    {evaluation ? (
                        <div className="space-y-4">
                            {(evaluation.grade ||
                                evaluation.points !== null) && (
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {evaluation.grade && (
                                        <div className="flex items-center gap-4 rounded-lg border p-4">
                                            <span className="text-4xl leading-none font-semibold">
                                                {evaluation.grade}
                                            </span>
                                            <div>
                                                <p className="text-sm font-medium">
                                                    Employee evaluation
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    A is the strongest, D the
                                                    weakest
                                                </p>
                                            </div>
                                        </div>
                                    )}
                                    {evaluation.points !== null && (
                                        <div className="flex flex-col justify-center gap-2.5 rounded-lg border p-4">
                                            <div className="flex items-baseline justify-between gap-3">
                                                <p className="text-sm font-medium">
                                                    Overall points
                                                </p>
                                                <p className="text-2xl leading-none font-semibold tabular-nums">
                                                    {evaluation.points}
                                                    <span className="text-sm font-normal text-muted-foreground">
                                                        {' '}
                                                        / 100
                                                    </span>
                                                </p>
                                            </div>
                                            <div
                                                className="h-1.5 overflow-hidden rounded-full bg-muted"
                                                aria-hidden
                                            >
                                                <div
                                                    className="h-full rounded-full bg-foreground/60"
                                                    style={{
                                                        width: `${evaluation.points}%`,
                                                    }}
                                                />
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}
                            {evaluation.ratings.length > 0 && (
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {evaluation.ratings.map((rating) => (
                                        <div
                                            key={rating.criterion.id}
                                            className="flex items-center justify-between gap-3 rounded-md border p-3"
                                        >
                                            <span className="text-sm font-medium">
                                                {rating.criterion.label}
                                            </span>
                                            <StarRating
                                                value={rating.rating}
                                                size="sm"
                                            />
                                        </div>
                                    ))}
                                </div>
                            )}
                            {evaluation.notes && (
                                <div className="space-y-1.5 rounded-lg bg-muted/40 p-4">
                                    <p className="text-xs font-medium text-muted-foreground uppercase">
                                        Notes
                                    </p>
                                    <p className="text-sm whitespace-pre-wrap">
                                        {evaluation.notes}
                                    </p>
                                </div>
                            )}
                            <p className="text-xs text-muted-foreground">
                                Submitted by{' '}
                                {evaluation.evaluator?.name ?? 'a manager'} on{' '}
                                {new Date(
                                    evaluation.submitted_at,
                                ).toLocaleDateString(undefined, {
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric',
                                })}
                            </p>
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            No evaluation on file — this trainee was added to
                            the Development Zone before the evaluation workflow
                            existed.
                        </p>
                    )}
                </Card>

                <div className="space-y-3">
                    <h2 className="font-semibold tracking-tight">
                        {trainee.status === 'pending'
                            ? 'Build a development plan'
                            : 'Development plan'}
                    </h2>
                    <DevelopmentPlanPanel
                        traineeId={trainee.id}
                        traineeName={trainee.name}
                        plan={developmentPlan}
                        picker={developmentPicker}
                        skillRatings={skillRatings}
                        readOnly={
                            !canManagePlan || trainee.archived_at !== null
                        }
                    />
                </div>
            </div>
        </>
    );
}

const STATUS_NOTES: Record<
    DevelopmentStatus,
    { icon: ReactNode; admin: string; viewer: string }
> = {
    pending: {
        icon: <Clock className="size-4" />,
        admin: 'Review the evaluation and pick plan items below — saving the plan makes it Active.',
        viewer: 'Waiting for an admin to review the evaluation and build the development plan.',
    },
    active: {
        icon: <CircleDot className="size-4" />,
        admin: 'Working through the development plan. Mark it completed once they’re done.',
        viewer: 'Working through the development plan.',
    },
    completed: {
        icon: <CheckCircle2 className="size-4" />,
        admin: 'Development completed. Need more work? Reopen to move them back to Active.',
        viewer: 'Development completed.',
    },
};

/** A one-line "where things stand / what's next" under the header. */
function StatusNote({
    status,
    canManage,
}: {
    status: DevelopmentStatus;
    canManage: boolean;
}) {
    const note = STATUS_NOTES[status];

    return (
        <p className="flex items-center gap-2 border-t border-border/60 px-5 py-2.5 text-sm text-muted-foreground">
            <span className="shrink-0">{note.icon}</span>
            {canManage ? note.admin : note.viewer}
        </p>
    );
}

DevelopmentZoneShow.layout = (page: {
    trainee: DevelopmentZoneShowData['trainee'];
}) => ({
    breadcrumbs: [
        { title: 'Development Zone', href: developmentZoneIndex() },
        {
            title: page.trainee.name,
            href: developmentZoneShow(page.trainee.id),
        },
    ] satisfies BreadcrumbItem[],
});
