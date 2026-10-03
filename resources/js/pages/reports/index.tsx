import { Head, router, usePage } from '@inertiajs/react';
import { FileSpreadsheet, FileText } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { ContentPanel } from '@/components/reports/content-panel';
import { OverviewPanel } from '@/components/reports/overview-panel';
import { StoresPanel } from '@/components/reports/stores-panel';
import { TraineesPanel } from '@/components/reports/trainees-panel';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useStoreFilter, useSyncStoreFilter } from '@/hooks/use-store-filter';
import { exportMethod, index } from '@/routes/reports';
import type { BreadcrumbItem } from '@/types';
import type {
    DevelopmentZoneSummary,
    DistributionBand,
    ImportanceRow,
    ManagerActivityRow,
    QuizResultsSummary,
    ReportArea,
    ReportFilters,
    ReportOverview,
    StationInsights,
    StorePerformanceRow,
    TraineeStatusReport,
    TrendPoint,
} from '@/types/reports';
import type { StoreOption } from '@/types/training';

type ReportPageProps = {
    isSuperAdmin: boolean;
    canChooseStore: boolean;
    storeOptions: StoreOption[];
    weekOptions: number[];
    filters: ReportFilters;
    overview: ReportOverview;
    trend?: TrendPoint[];
    distribution?: DistributionBand[];
    developmentZone?: DevelopmentZoneSummary;
    storePerformance?: StorePerformanceRow[];
    managerActivity?: ManagerActivityRow[];
    traineeStatus?: TraineeStatusReport;
    stationInsights?: StationInsights;
    importanceBreakdown?: ImportanceRow[];
    quizSummary?: QuizResultsSummary;
};

const AREAS: { value: ReportArea; label: string }[] = [
    { value: 'overview', label: 'Overview' },
    { value: 'stores', label: 'Stores & Trainers' },
    { value: 'trainees', label: 'Trainees' },
    { value: 'content', label: 'Content' },
];

export default function ReportsIndex() {
    const props = usePage<ReportPageProps>().props;
    const {
        isSuperAdmin,
        canChooseStore,
        storeOptions,
        weekOptions,
        filters,
        overview,
    } = props;

    const [area, setArea] = useState<ReportArea>('overview');

    const { setSelectedStoreId } = useStoreFilter();
    useSyncStoreFilter(filters.store);

    const csvReportForArea: Record<ReportArea, string> = {
        overview: 'trainees',
        stores: 'stores',
        trainees: 'trainees',
        content: 'stations',
    };

    function exportHref(format: 'csv' | 'pdf') {
        const params = new URLSearchParams({ format });

        if (format === 'csv') {
            params.set('report', csvReportForArea[area]);
        }

        if (filters.store) {
            params.set('store', String(filters.store));
        }

        if (filters.weeks) {
            params.set('weeks', String(filters.weeks));
        }

        if (filters.includeArchived) {
            params.set('includeArchived', '1');
        }

        return `${exportMethod().url}?${params.toString()}`;
    }

    function applyFilters(next: {
        store?: string;
        weeks?: string;
        includeArchived?: boolean;
    }) {
        const store =
            next.store ?? (filters.store ? String(filters.store) : 'all');
        const weeks = next.weeks ?? String(filters.weeks);
        const includeArchived = next.includeArchived ?? filters.includeArchived;

        if (next.store !== undefined) {
            setSelectedStoreId(store === 'all' ? null : Number(store));
        }

        const params: Record<string, string> = {};

        if (store !== 'all') {
            params.store = store;
        }

        if (weeks !== String(weekOptions[0])) {
            params.weeks = weeks;
        }

        if (includeArchived) {
            params.includeArchived = '1';
        }

        router.get(index().url, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    return (
        <>
            <Head title="Reports" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title="Reports"
                        description="Track training results across your trainees, stores, and stations."
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        {canChooseStore && storeOptions.length > 0 && (
                            <Select
                                value={
                                    filters.store
                                        ? String(filters.store)
                                        : 'all'
                                }
                                onValueChange={(store) =>
                                    applyFilters({ store })
                                }
                            >
                                <SelectTrigger className="w-40">
                                    <SelectValue placeholder="All stores" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All stores
                                    </SelectItem>
                                    {storeOptions.map((store) => (
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
                        <Select
                            value={String(filters.weeks)}
                            onValueChange={(weeks) => applyFilters({ weeks })}
                        >
                            <SelectTrigger className="w-36">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {weekOptions.map((weeks) => (
                                    <SelectItem
                                        key={weeks}
                                        value={String(weeks)}
                                    >
                                        Last {weeks} weeks
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Label className="flex items-center gap-2 text-sm font-normal text-muted-foreground">
                            <Checkbox
                                checked={filters.includeArchived}
                                onCheckedChange={(checked) =>
                                    applyFilters({
                                        includeArchived: checked === true,
                                    })
                                }
                            />
                            Include archived
                        </Label>
                        <Button variant="outline" size="icon" asChild>
                            <a
                                href={exportHref('csv')}
                                title="Export current view as CSV"
                            >
                                <FileSpreadsheet className="size-4" />
                            </a>
                        </Button>
                        <Button variant="outline" size="icon" asChild>
                            <a
                                href={exportHref('pdf')}
                                title="Export summary as PDF"
                            >
                                <FileText className="size-4" />
                            </a>
                        </Button>
                    </div>
                </div>

                <ToggleGroup
                    type="single"
                    variant="outline"
                    value={area}
                    onValueChange={(value) =>
                        value && setArea(value as ReportArea)
                    }
                    className="w-full justify-start overflow-x-auto sm:w-auto"
                >
                    {AREAS.map((item) => (
                        <ToggleGroupItem
                            key={item.value}
                            value={item.value}
                            className="px-4"
                        >
                            {item.label}
                        </ToggleGroupItem>
                    ))}
                </ToggleGroup>

                {area === 'overview' && (
                    <OverviewPanel
                        overview={overview}
                        trend={props.trend}
                        distribution={props.distribution}
                        developmentZone={props.developmentZone}
                    />
                )}
                {area === 'stores' && (
                    <StoresPanel
                        stores={props.storePerformance}
                        managers={props.managerActivity}
                        isSuperAdmin={isSuperAdmin}
                    />
                )}
                {area === 'trainees' && (
                    <TraineesPanel report={props.traineeStatus} />
                )}
                {area === 'content' && (
                    <ContentPanel
                        insights={props.stationInsights}
                        importance={props.importanceBreakdown}
                        quiz={props.quizSummary}
                    />
                )}
            </div>
        </>
    );
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Reports', href: index() }];

ReportsIndex.layout = { breadcrumbs };
