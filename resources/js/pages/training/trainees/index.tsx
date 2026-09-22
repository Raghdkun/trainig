import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus, Users } from 'lucide-react';
import Heading from '@/components/heading';
import { CompletionBar } from '@/components/training/completion-bar';
import { RatingMeter } from '@/components/training/rating-meter';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useStoreFilter, useSyncStoreFilter } from '@/hooks/use-store-filter';
import { create, index, show } from '@/routes/trainees';
import type { BreadcrumbItem } from '@/types';
import type {
    StoreOption,
    TraineeStatusCounts,
    TraineeSummary,
} from '@/types/training';

type TraineeTab = 'active' | 'archived';

export default function TraineesIndex() {
    const { trainees, stores, filters, canChooseStore, traineeCounts } =
        usePage<{
            trainees: TraineeSummary[];
            stores: StoreOption[];
            filters: { store: number | null; tab: TraineeTab };
            canChooseStore: boolean;
            traineeCounts: TraineeStatusCounts;
        }>().props;

    const { setSelectedStoreId } = useStoreFilter();
    useSyncStoreFilter(filters.store);

    function applyFilters(next: { store?: string; tab?: TraineeTab }) {
        const store =
            next.store ?? (filters.store ? String(filters.store) : 'all');
        const tab = next.tab ?? filters.tab;

        if (next.store !== undefined) {
            setSelectedStoreId(store === 'all' ? null : Number(store));
        }

        const params: Record<string, string> = {};

        if (store !== 'all') {
            params.store = store;
        }

        if (tab !== 'active') {
            params.tab = tab;
        }

        router.get(index().url, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    return (
        <>
            <Head title="Trainees" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title="Trainees"
                        description="Track each crew member's progress through the training program."
                    />
                    <div className="flex items-center gap-2">
                        {canChooseStore && stores.length > 0 && (
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
                        <Button asChild>
                            <Link href={create().url}>
                                <Plus className="size-4" />
                                <span className="hidden sm:inline">
                                    Add trainee
                                </span>
                            </Link>
                        </Button>
                    </div>
                </div>

                <ToggleGroup
                    type="single"
                    variant="outline"
                    value={filters.tab}
                    onValueChange={(tab) =>
                        tab && applyFilters({ tab: tab as TraineeTab })
                    }
                    className="w-full justify-start overflow-x-auto sm:w-auto"
                >
                    <ToggleGroupItem value="active">
                        Active ({traineeCounts.active})
                    </ToggleGroupItem>
                    <ToggleGroupItem value="archived">
                        Archived ({traineeCounts.archived})
                    </ToggleGroupItem>
                </ToggleGroup>

                {trainees.length === 0 ? (
                    <Card className="flex flex-col items-center justify-center gap-3 border-dashed p-12 text-center">
                        <Users className="size-10 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            {filters.tab === 'archived'
                                ? 'No archived trainees.'
                                : 'No trainees yet. Add a trainee to start tracking their training.'}
                        </p>
                    </Card>
                ) : (
                    <section className="surface-tray">
                        <ul className="surface-core divide-y divide-border/60 overflow-hidden">
                            {trainees.map((trainee) => (
                                <li key={trainee.id}>
                                    <Link
                                        href={show(trainee.id).url}
                                        className="flex flex-col gap-3 p-4 transition-colors hover:bg-muted/50 sm:flex-row sm:items-center sm:gap-4"
                                    >
                                        <div className="min-w-0 sm:flex-1">
                                            <p className="truncate font-medium">
                                                {trainee.name}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {[
                                                    trainee.position,
                                                    canChooseStore
                                                        ? trainee.store.name
                                                        : null,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ') || '—'}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-3 sm:w-auto">
                                            <div className="w-full sm:w-44">
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
                    </section>
                )}
            </div>
        </>
    );
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Trainees', href: index() }];

TraineesIndex.layout = { breadcrumbs };
