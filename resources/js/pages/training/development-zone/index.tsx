import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ChevronRight,
    ClipboardCheck,
    TrendingUp,
    UserPlus,
} from 'lucide-react';
import { useState } from 'react';
import { CompletionBar } from '@/components/training/completion-bar';
import { DevelopmentStatusBadge } from '@/components/training/development-status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useInitials } from '@/hooks/use-initials';
import { useStoreFilter, useSyncStoreFilter } from '@/hooks/use-store-filter';
import { cn } from '@/lib/utils';
import { create, index, show } from '@/routes/development-zone';
import { assessmentSetup } from '@/routes/training';
import type { BreadcrumbItem } from '@/types';
import { DEVELOPMENT_STATUS_LABELS } from '@/types/training';
import type {
    DevelopmentStatus,
    DevelopmentZoneTrainee,
    StoreOption,
} from '@/types/training';

type StatusFilter = 'all' | DevelopmentStatus;

const STATUS_FILTERS: StatusFilter[] = [
    'all',
    'pending',
    'active',
    'completed',
];

export default function DevelopmentZoneIndex() {
    const {
        trainees,
        stores,
        filters,
        canChooseStore,
        canAddToZone,
        canSetupAssessment,
    } = usePage<{
        trainees: DevelopmentZoneTrainee[];
        stores: StoreOption[];
        filters: { store: number | null };
        canChooseStore: boolean;
        canAddToZone: boolean;
        canSetupAssessment: boolean;
    }>().props;

    const { setSelectedStoreId } = useStoreFilter();
    useSyncStoreFilter(filters.store);
    const getInitials = useInitials();
    const [status, setStatus] = useState<StatusFilter>('all');

    function filterStore(value: string) {
        const storeId = value === 'all' ? null : Number(value);
        setSelectedStoreId(storeId);
        router.get(index().url, storeId ? { store: storeId } : {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    const countFor = (filter: StatusFilter) =>
        filter === 'all'
            ? trainees.length
            : trainees.filter((trainee) => trainee.status === filter).length;
    const visible =
        status === 'all'
            ? trainees
            : trainees.filter((trainee) => trainee.status === status);

    return (
        <>
            <Head title="Development Zone" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1.5">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Development Zone
                        </h1>
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            Evaluate employees who need extra coaching, and
                            track their progress on a focused plan.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {canSetupAssessment && (
                            <Button variant="outline" asChild>
                                <Link href={assessmentSetup().url}>
                                    <ClipboardCheck className="size-4" />{' '}
                                    Assessment setup
                                </Link>
                            </Button>
                        )}
                        {canAddToZone && (
                            <Button asChild>
                                <Link href={create().url}>
                                    <UserPlus className="size-4" /> Add to
                                    Development Zone
                                </Link>
                            </Button>
                        )}
                    </div>
                </header>

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div
                        role="tablist"
                        aria-label="Filter by status"
                        className="inline-flex flex-wrap gap-1 rounded-lg border p-1"
                    >
                        {STATUS_FILTERS.map((filter) => (
                            <button
                                key={filter}
                                type="button"
                                role="tab"
                                aria-selected={status === filter}
                                onClick={() => setStatus(filter)}
                                className={cn(
                                    'flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                    status === filter
                                        ? 'bg-accent text-foreground'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {filter === 'all'
                                    ? 'All'
                                    : DEVELOPMENT_STATUS_LABELS[filter]}
                                <span className="text-xs text-muted-foreground tabular-nums">
                                    {countFor(filter)}
                                </span>
                            </button>
                        ))}
                    </div>
                    {canChooseStore && stores.length > 0 && (
                        <Select
                            value={
                                filters.store ? String(filters.store) : 'all'
                            }
                            onValueChange={filterStore}
                        >
                            <SelectTrigger className="w-44">
                                <SelectValue placeholder="All stores" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All stores</SelectItem>
                                {stores.map((store) => (
                                    <SelectItem
                                        key={store.id}
                                        value={String(store.id)}
                                    >
                                        {store.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                </div>

                {trainees.length === 0 ? (
                    <Card className="flex flex-col items-center justify-center gap-3 border-dashed p-12 text-center shadow-none">
                        <TrendingUp className="size-10 text-muted-foreground" />
                        <p className="font-medium">
                            No one is in the Development Zone yet
                        </p>
                        <p className="max-w-sm text-sm text-muted-foreground">
                            Add a trainee or a new employee with an evaluation
                            to build them a focused plan.
                        </p>
                    </Card>
                ) : visible.length === 0 ? (
                    <Card className="items-center justify-center border-dashed p-10 text-center text-sm text-muted-foreground shadow-none">
                        No one is{' '}
                        {DEVELOPMENT_STATUS_LABELS[
                            status as DevelopmentStatus
                        ].toLowerCase()}{' '}
                        right now.
                    </Card>
                ) : (
                    <Card className="gap-0 overflow-hidden p-0">
                        <ul className="divide-y divide-border/60">
                            {visible.map((trainee) => (
                                <li key={trainee.id}>
                                    <Link
                                        href={show(trainee.id).url}
                                        className="group flex flex-col gap-3 px-5 py-4 transition-colors hover:bg-muted/40 sm:flex-row sm:items-center sm:gap-5"
                                    >
                                        <div className="flex min-w-0 items-center gap-3 sm:flex-1">
                                            <span
                                                aria-hidden
                                                className="flex size-9 shrink-0 items-center justify-center rounded-full border text-xs font-semibold text-muted-foreground"
                                            >
                                                {getInitials(trainee.name)}
                                            </span>
                                            <div className="min-w-0 space-y-0.5">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="truncate font-medium">
                                                        {trainee.name}
                                                    </p>
                                                    <DevelopmentStatusBadge
                                                        status={trainee.status}
                                                    />
                                                    {trainee.development_only && (
                                                        <Badge variant="outline">
                                                            New employee
                                                        </Badge>
                                                    )}
                                                </div>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {[
                                                        trainee.position,
                                                        trainee.store.name,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ') || '—'}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-3 pl-12 sm:w-64 sm:pl-0">
                                            {trainee.stats.total === 0 ? (
                                                <p className="flex-1 text-xs text-muted-foreground">
                                                    {trainee.status ===
                                                    'pending'
                                                        ? 'Waiting for a plan'
                                                        : 'No plan items'}
                                                </p>
                                            ) : (
                                                <div className="flex-1 space-y-1">
                                                    <p className="text-xs text-muted-foreground tabular-nums">
                                                        {
                                                            trainee.stats
                                                                .completed
                                                        }{' '}
                                                        of {trainee.stats.total}{' '}
                                                        plan items
                                                    </p>
                                                    <CompletionBar
                                                        completed={
                                                            trainee.stats
                                                                .completed
                                                        }
                                                        total={
                                                            trainee.stats.total
                                                        }
                                                    />
                                                </div>
                                            )}
                                            <ChevronRight className="size-4 shrink-0 text-muted-foreground/60 transition-transform group-hover:translate-x-0.5" />
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </Card>
                )}
            </div>
        </>
    );
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Development Zone', href: index() },
];

DevelopmentZoneIndex.layout = { breadcrumbs };
