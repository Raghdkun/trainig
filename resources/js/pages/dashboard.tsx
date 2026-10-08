import { Head, Link, usePage } from '@inertiajs/react';
import {
    Building2,
    Layers,
    ListChecks,
    Percent,
    Store as StoreIcon,
    Users,
} from 'lucide-react';
import { DevelopmentZonePanel } from '@/components/dashboard/development-zone-panel';
import { HeroTile } from '@/components/dashboard/hero-tile';
import { StatCard } from '@/components/dashboard/stat-card';
import { CompletionBar } from '@/components/training/completion-bar';
import { RatingMeter } from '@/components/training/rating-meter';
import { Button } from '@/components/ui/button';
import { useSyncStoreFilter } from '@/hooks/use-store-filter';
import { dashboard } from '@/routes';
import { management } from '@/routes/admin';
import { index as traineesIndex, show as traineeShow } from '@/routes/trainees';
import type { Auth, BreadcrumbItem } from '@/types';
import type {
    DashboardStats,
    DevelopmentZoneTrainee,
    ManagerStats,
    TraineeSummary,
} from '@/types/training';

type DashboardProps = {
    isSuperAdmin: boolean;
    filters: { store: number | null };
    stats?: DashboardStats;
    managerStats?: ManagerStats;
    trainees?: TraineeSummary[];
    developmentZone: DevelopmentZoneTrainee[];
};

const GLASS_BUTTON =
    'border border-white/25 bg-white/15 text-primary-foreground shadow-none backdrop-blur hover:bg-white/25';

export default function Dashboard() {
    const page = usePage<DashboardProps & { auth: Auth }>().props;
    const firstName = page.auth.user.name.split(' ')[0];

    useSyncStoreFilter(page.filters.store);

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
                {page.isSuperAdmin ? (
                    <SuperAdminView {...page} firstName={firstName} />
                ) : (
                    <ManagerView {...page} firstName={firstName} />
                )}
            </div>
        </>
    );
}

function SuperAdminView({
    stats,
    developmentZone,
    firstName,
}: DashboardProps & { firstName: string }) {
    return (
        <div className="flex flex-col gap-4">
            <div className="grid grid-cols-2 gap-4 lg:auto-rows-fr lg:grid-cols-4">
                <HeroTile
                    className="col-span-2 lg:row-span-2"
                    eyebrow="Control center"
                    title={`Welcome back, ${firstName}`}
                    subtitle="Manage your team, stores, and the training program — all in one place."
                    metricValue={stats?.trainees ?? 0}
                    metricLabel="trainees in training"
                    action={
                        <Button asChild className={GLASS_BUTTON}>
                            <Link href={management().url}>
                                <Building2 className="size-4" /> Manage team
                                &amp; stores
                            </Link>
                        </Button>
                    }
                />
                <StatCard
                    label="Stores"
                    value={stats?.stores ?? 0}
                    icon={StoreIcon}
                />
                <StatCard label="Team" value={stats?.users ?? 0} icon={Users} />
                <StatCard
                    label="Stations"
                    value={stats?.sections ?? 0}
                    icon={Layers}
                />
                <StatCard
                    label="Checklist items"
                    value={stats?.items ?? 0}
                    icon={ListChecks}
                />
            </div>

            <DevelopmentZonePanel trainees={developmentZone} />
        </div>
    );
}

function ManagerView({
    managerStats,
    trainees = [],
    developmentZone,
    firstName,
}: DashboardProps & { firstName: string }) {
    return (
        <div className="flex flex-col gap-4">
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-4">
                <HeroTile
                    className="lg:col-span-2"
                    eyebrow="Your overview"
                    title={`Welcome back, ${firstName}`}
                    subtitle="Track your trainees and keep their training moving."
                    metricValue={`${managerStats?.completion ?? 0}%`}
                    metricLabel="average completion"
                    action={
                        <Button asChild className={GLASS_BUTTON}>
                            <Link href={traineesIndex().url}>
                                View trainees
                            </Link>
                        </Button>
                    }
                />
                <StatCard
                    label="My trainees"
                    value={managerStats?.trainees ?? 0}
                    icon={Users}
                />
                <StatCard
                    label="Avg score"
                    value={
                        managerStats?.average_rating != null
                            ? `${Math.round(managerStats.average_rating)}%`
                            : '—'
                    }
                    icon={Percent}
                />
            </div>

            <section className="surface-tray">
                <div className="surface-core overflow-hidden">
                    <header className="flex items-center justify-between gap-4 border-b border-border/60 p-4">
                        <h2 className="font-semibold tracking-tight">
                            Your trainees
                        </h2>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={traineesIndex().url}>View all</Link>
                        </Button>
                    </header>

                    {trainees.length === 0 ? (
                        <p className="p-8 text-center text-sm text-muted-foreground">
                            No trainees assigned yet.
                        </p>
                    ) : (
                        <ul className="divide-y divide-border/60">
                            {trainees.slice(0, 6).map((trainee) => (
                                <li key={trainee.id}>
                                    <Link
                                        href={traineeShow(trainee.id).url}
                                        className="flex flex-col gap-3 p-4 transition-colors hover:bg-muted/50 sm:flex-row sm:items-center sm:gap-4"
                                    >
                                        <div className="min-w-0 sm:flex-1">
                                            <p className="truncate font-medium">
                                                {trainee.name}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {trainee.position ??
                                                    trainee.store.name}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <div className="w-full sm:w-40">
                                                <CompletionBar
                                                    completed={
                                                        trainee.stats.completed
                                                    }
                                                    total={trainee.stats.total}
                                                />
                                            </div>
                                            <RatingMeter
                                                value={
                                                    trainee.stats.average_rating
                                                }
                                            />
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </section>

            <DevelopmentZonePanel trainees={developmentZone} />
        </div>
    );
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
];

Dashboard.layout = { breadcrumbs };
