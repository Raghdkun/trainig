import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, Trash2, User } from 'lucide-react';
import { DevelopmentPlanPanel } from '@/components/training/development-plan-panel';
import { DevelopmentStatusBadge } from '@/components/training/development-status-badge';
import { StarRating } from '@/components/training/star-rating';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    index as developmentZoneIndex,
    show as developmentZoneShow,
} from '@/routes/development-zone';
import { show as traineeShow } from '@/routes/trainees';
import { complete, destroy } from '@/routes/trainees/development';
import type { BreadcrumbItem } from '@/types';
import type { DevelopmentZoneShowData } from '@/types/training';

export default function DevelopmentZoneShow() {
    const {
        trainee,
        evaluation,
        developmentPlan,
        developmentPicker,
        canManagePlan,
        canComplete,
        canRemove,
    } = usePage<DevelopmentZoneShowData>().props;

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

                <Card className="gap-4 p-5">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-xl font-semibold tracking-tight">
                                    {trainee.name}
                                </h1>
                                <DevelopmentStatusBadge
                                    status={trainee.status}
                                />
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {[trainee.position, trainee.store.name]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </p>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" size="sm" asChild>
                                <Link href={traineeShow(trainee.id).url}>
                                    <User className="size-4" /> View profile
                                </Link>
                            </Button>
                            {canComplete && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={trainee.status !== 'active'}
                                    onClick={() =>
                                        router.patch(
                                            complete(trainee.id).url,
                                            {},
                                            {
                                                preserveScroll: true,
                                                onSuccess: () =>
                                                    router.flushAll(),
                                            },
                                        )
                                    }
                                >
                                    <CheckCircle2 className="size-4" /> Mark
                                    Completed
                                </Button>
                            )}
                            {canRemove && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="text-muted-foreground hover:text-destructive"
                                    onClick={() =>
                                        router.delete(destroy(trainee.id).url, {
                                            preserveScroll: true,
                                            onSuccess: () => {
                                                router.flushAll();
                                                router.visit(
                                                    developmentZoneIndex().url,
                                                );
                                            },
                                        })
                                    }
                                >
                                    <Trash2 className="size-4" /> Remove from
                                    Development Zone
                                </Button>
                            )}
                        </div>
                    </div>
                </Card>

                <Card className="gap-4 p-5">
                    <h2 className="font-semibold tracking-tight">
                        Manager evaluation
                    </h2>
                    {evaluation ? (
                        <div className="space-y-4">
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
                            {evaluation.notes && (
                                <div className="space-y-1">
                                    <p className="text-xs text-muted-foreground">
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
                        readOnly={
                            !canManagePlan || trainee.archived_at !== null
                        }
                    />
                </div>
            </div>
        </>
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
