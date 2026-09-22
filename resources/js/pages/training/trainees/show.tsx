import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArchiveRestore,
    ArrowLeft,
    Archive as ArchiveIcon,
    Pencil,
    Trash2,
    Users,
} from 'lucide-react';
import { AssignManagersDialog } from '@/components/training/assign-managers-dialog';
import { ChecklistSections } from '@/components/training/checklist-sections';
import { CompletionBar } from '@/components/training/completion-bar';
import { ConfirmDeleteDialog } from '@/components/training/confirm-delete-dialog';
import { RatingMeter } from '@/components/training/rating-meter';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    archive,
    destroy,
    edit,
    index,
    restore,
    show,
} from '@/routes/trainees';
import type { BreadcrumbItem } from '@/types';
import type { TraineeDetail, TraineeProgressData } from '@/types/training';

export default function TraineeShow() {
    const {
        trainee,
        progress,
        canManage,
        canDelete,
        canAssignManagers,
        availableManagers,
    } = usePage<{
        trainee: TraineeDetail;
        progress: TraineeProgressData;
        canManage: boolean;
        canDelete: boolean;
        canAssignManagers: boolean;
        availableManagers: { id: number; name: string }[];
    }>().props;

    const { stats } = progress;
    const isArchived = trainee.archived_at !== null;

    return (
        <>
            <Head title={trainee.name} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <Link
                    href={index().url}
                    className="flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" /> Trainees
                </Link>

                {isArchived && (
                    <Alert>
                        <ArchiveIcon />
                        <AlertTitle>
                            Archived on{' '}
                            {new Date(
                                trainee.archived_at as string,
                            ).toLocaleDateString(undefined, {
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric',
                            })}
                            {trainee.archived_by
                                ? ` by ${trainee.archived_by.name}`
                                : ''}
                        </AlertTitle>
                        <AlertDescription>
                            <p>
                                This trainee is read-only.{' '}
                                {canManage
                                    ? 'Restore them to active to resume scoring.'
                                    : 'Only an admin can make changes to a trainee in History.'}
                            </p>
                            {canManage && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    className="mt-1"
                                    onClick={() =>
                                        router.patch(
                                            restore(trainee.id).url,
                                            {},
                                            {
                                                // The Trainees list and Dashboard are
                                                // reached via prefetching nav links —
                                                // without this they'd keep serving
                                                // their cached (now-stale) counts.
                                                onSuccess: () =>
                                                    router.flushAll(),
                                            },
                                        )
                                    }
                                >
                                    <ArchiveRestore className="size-4" />{' '}
                                    Restore to active
                                </Button>
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                <Card className="gap-4 p-5">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h1 className="text-xl font-semibold tracking-tight">
                                {trainee.name}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {[trainee.position, trainee.store.name]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </p>
                            {trainee.managers.length > 0 && (
                                <div className="mt-2 flex flex-wrap items-center gap-1.5">
                                    <Users className="size-3.5 text-muted-foreground" />
                                    {trainee.managers.map((m) => (
                                        <Badge key={m.id} variant="secondary">
                                            {m.name}
                                        </Badge>
                                    ))}
                                </div>
                            )}
                        </div>

                        <div className="flex flex-wrap gap-2">
                            {canAssignManagers && (
                                <AssignManagersDialog
                                    traineeId={trainee.id}
                                    available={availableManagers}
                                    assignedIds={trainee.managers.map(
                                        (m) => m.id,
                                    )}
                                    trigger={
                                        <Button variant="outline" size="sm">
                                            <Users className="size-4" />{' '}
                                            Managers
                                        </Button>
                                    }
                                />
                            )}
                            {!isArchived && canManage && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        router.patch(
                                            archive(trainee.id).url,
                                            {},
                                            {
                                                onSuccess: () =>
                                                    router.flushAll(),
                                            },
                                        )
                                    }
                                >
                                    <ArchiveIcon className="size-4" /> Mark
                                    complete &amp; archive
                                </Button>
                            )}
                            {canManage && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={edit(trainee.id).url}>
                                        <Pencil className="size-4" /> Edit
                                    </Link>
                                </Button>
                            )}
                            {canDelete && (
                                <ConfirmDeleteDialog
                                    title="Remove trainee?"
                                    description="This permanently deletes the trainee and all of their evaluation records."
                                    onConfirm={(close) =>
                                        router.delete(destroy(trainee.id).url, {
                                            onSuccess: close,
                                        })
                                    }
                                    trigger={
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="text-muted-foreground hover:text-destructive"
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    }
                                />
                            )}
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-1">
                            <p className="text-xs text-muted-foreground">
                                Completion
                            </p>
                            <CompletionBar
                                completed={stats.completed}
                                total={stats.total}
                            />
                            <p className="text-xs text-muted-foreground">
                                {stats.completed} of {stats.total} steps
                                complete
                            </p>
                        </div>
                        <div className="space-y-1">
                            <p className="text-xs text-muted-foreground">
                                Average score
                            </p>
                            <RatingMeter
                                value={stats.average_rating}
                                size="md"
                            />
                        </div>
                    </div>
                </Card>

                <ChecklistSections
                    sections={progress.sections}
                    traineeId={trainee.id}
                    traineeName={trainee.name}
                    readOnly={isArchived}
                    currentStepId={progress.currentStepId}
                />
            </div>
        </>
    );
}

TraineeShow.layout = (page: { trainee: TraineeDetail }) => ({
    breadcrumbs: [
        { title: 'Trainees', href: index() },
        { title: page.trainee.name, href: show(page.trainee.id) },
    ] satisfies BreadcrumbItem[],
});
