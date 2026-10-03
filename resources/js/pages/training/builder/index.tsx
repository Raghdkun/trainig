import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ClipboardList,
    Clock,
    GripVertical,
    Layers,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import Heading from '@/components/heading';
import { ConfirmDeleteDialog } from '@/components/training/confirm-delete-dialog';
import { SectionFormDialog } from '@/components/training/section-form-dialog';
import { SectionStatusBadge } from '@/components/training/section-status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useSortable } from '@/hooks/use-sortable';
import { destroy, edit, index, reorder } from '@/routes/training/sections';
import type { BreadcrumbItem } from '@/types';
import type { Section, SectionStatusCounts } from '@/types/training';

type StatusFilter = 'all' | 'draft' | 'published';

export default function BuilderIndex() {
    const { sections, filters, statusCounts } = usePage<{
        sections: Section[];
        filters: { status: string | null };
        statusCounts: SectionStatusCounts;
    }>().props;

    const status = (filters.status ?? 'all') as StatusFilter;

    function filterStatus(value: string) {
        if (!value) {
            return;
        }

        router.get(index().url, value === 'all' ? {} : { status: value }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    const { list, itemProps } = useSortable(sections, (payload) =>
        router.post(
            reorder().url,
            { items: payload },
            { preserveScroll: true },
        ),
    );

    return (
        <>
            <Head title="Content builder" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title="Content builder"
                        description="Build the standardized training program. Drag to reorder."
                    />
                    <SectionFormDialog
                        trigger={
                            <Button>
                                <Plus className="size-4" /> New station
                            </Button>
                        }
                    />
                </div>

                <ToggleGroup
                    type="single"
                    variant="outline"
                    value={status}
                    onValueChange={filterStatus}
                    className="w-full justify-start overflow-x-auto sm:w-auto"
                >
                    <ToggleGroupItem value="all">
                        All ({statusCounts.all})
                    </ToggleGroupItem>
                    <ToggleGroupItem value="draft">
                        Draft ({statusCounts.draft})
                    </ToggleGroupItem>
                    <ToggleGroupItem value="published">
                        Published ({statusCounts.published})
                    </ToggleGroupItem>
                </ToggleGroup>

                {list.length === 0 ? (
                    <Card className="flex flex-col items-center justify-center gap-3 border-dashed p-12 text-center">
                        <ClipboardList className="size-10 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            {status === 'all'
                                ? 'No stations yet. Create your first training station to get started.'
                                : `No ${status} stations.`}
                        </p>
                    </Card>
                ) : (
                    <div className="grid gap-3">
                        {list.map((section) => (
                            <Card
                                key={section.id}
                                {...itemProps(section.id)}
                                className="flex min-w-0 flex-row items-center gap-4 p-4 transition-colors transition-opacity hover:bg-muted/30 data-[dragging]:opacity-50"
                            >
                                <GripVertical className="size-5 shrink-0 cursor-grab text-muted-foreground" />

                                <Link
                                    href={edit(section.id).url}
                                    className="min-w-0 flex-1"
                                >
                                    <span className="truncate font-medium">
                                        {section.title}
                                    </span>
                                    {section.description && (
                                        <p className="mt-0.5 line-clamp-1 text-sm text-muted-foreground">
                                            {section.description}
                                        </p>
                                    )}
                                    <div className="mt-2 flex flex-wrap gap-1.5">
                                        <SectionStatusBadge
                                            status={section.status}
                                        />
                                        <Badge variant="secondary">
                                            <Layers className="size-3" />
                                            {section.categories_count ?? 0}{' '}
                                            categories
                                        </Badge>
                                        <Badge variant="secondary">
                                            {section.checklist_items_count ?? 0}{' '}
                                            items
                                        </Badge>
                                        <Badge variant="outline">
                                            <Clock className="size-3" />
                                            Hands-on:{' '}
                                            {section.hands_on_shifts || '—'}
                                        </Badge>
                                    </div>
                                </Link>

                                <div className="flex shrink-0 items-center gap-1">
                                    <SectionFormDialog
                                        section={section}
                                        trigger={
                                            <Button variant="ghost" size="icon">
                                                <Pencil className="size-4" />
                                            </Button>
                                        }
                                    />
                                    <ConfirmDeleteDialog
                                        title="Delete station?"
                                        description="This permanently deletes the station and all its categories, items, and media."
                                        onConfirm={(close) =>
                                            router.delete(
                                                destroy(section.id).url,
                                                {
                                                    preserveScroll: true,
                                                    onSuccess: close,
                                                },
                                            )
                                        }
                                        trigger={
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="text-muted-foreground hover:text-destructive"
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        }
                                    />
                                </div>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Content builder', href: index() },
];

BuilderIndex.layout = { breadcrumbs };
