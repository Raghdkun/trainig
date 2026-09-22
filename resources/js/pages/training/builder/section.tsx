import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Eye, EyeOff, Layers, Pencil, Plus } from 'lucide-react';
import { useCallback, useState } from 'react';
import Heading from '@/components/heading';
import { BuilderSelectionProvider } from '@/components/training/builder-selection';
import type { SelectionKind } from '@/components/training/builder-selection';
import { BulkActionBar } from '@/components/training/bulk-action-bar';
import { CategoryBlock } from '@/components/training/category-block';
import { CategoryFormDialog } from '@/components/training/category-form-dialog';
import { QuizManager } from '@/components/training/quiz-manager';
import { SectionFormDialog } from '@/components/training/section-form-dialog';
import { SectionStatusBadge } from '@/components/training/section-status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { useSortable } from '@/hooks/use-sortable';
import { reorder } from '@/routes/training/categories';
import { edit, index, publish, unpublish } from '@/routes/training/sections';
import type { BreadcrumbItem } from '@/types';
import type { MoveTarget, Section } from '@/types/training';

export default function BuilderSection() {
    const { section, moveTargets } = usePage<{
        section: Section;
        moveTargets: MoveTarget[];
    }>().props;
    const categories = section.categories ?? [];

    const [selectedCategories, setSelectedCategories] = useState<Set<number>>(
        () => new Set(),
    );
    const [selectedItems, setSelectedItems] = useState<Set<number>>(
        () => new Set(),
    );

    const toggle = useCallback((kind: SelectionKind, id: number) => {
        const setter =
            kind === 'category' ? setSelectedCategories : setSelectedItems;

        setter((prev) => {
            const next = new Set(prev);

            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });
    }, []);

    const isSelected = useCallback(
        (kind: SelectionKind, id: number) =>
            (kind === 'category' ? selectedCategories : selectedItems).has(id),
        [selectedCategories, selectedItems],
    );

    const clearSelection = useCallback(() => {
        setSelectedCategories(new Set());
        setSelectedItems(new Set());
    }, []);

    const { list, itemProps } = useSortable(categories, (payload) =>
        router.post(
            reorder(section.id).url,
            { items: payload },
            { preserveScroll: true },
        ),
    );

    const timing = [
        section.pie_content_review && `Review: ${section.pie_content_review}`,
        section.screen_to_shoulder &&
            `Screen-to-shoulder: ${section.screen_to_shoulder}`,
        section.hands_on_shifts && `Hands-on: ${section.hands_on_shifts}`,
    ].filter(Boolean);

    return (
        <>
            <Head title={section.title} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <Link
                    href={index().url}
                    className="flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" /> Content builder
                </Link>

                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0 space-y-2">
                        <Heading
                            title={section.title}
                            description={section.description ?? undefined}
                        />
                        <div className="flex flex-wrap gap-1.5">
                            <SectionStatusBadge status={section.status} />
                            {timing.map((label) => (
                                <Badge key={label} variant="outline">
                                    {label}
                                </Badge>
                            ))}
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {section.status === 'published' ? (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.patch(
                                        unpublish(section.id).url,
                                        {},
                                        {
                                            preserveScroll: true,
                                            // The Content Builder list and
                                            // Dashboard "Stations" count are
                                            // reached via prefetching nav
                                            // links — flush so they don't
                                            // keep serving stale counts.
                                            onSuccess: () => router.flushAll(),
                                        },
                                    )
                                }
                            >
                                <EyeOff className="size-4" /> Unpublish
                            </Button>
                        ) : (
                            <Button
                                onClick={() =>
                                    router.patch(
                                        publish(section.id).url,
                                        {},
                                        {
                                            preserveScroll: true,
                                            onSuccess: () => router.flushAll(),
                                        },
                                    )
                                }
                            >
                                <Eye className="size-4" /> Publish
                            </Button>
                        )}
                        <SectionFormDialog
                            section={section}
                            trigger={
                                <Button variant="outline">
                                    <Pencil className="size-4" /> Edit station
                                </Button>
                            }
                        />
                        <CategoryFormDialog
                            sectionId={section.id}
                            trigger={
                                <Button>
                                    <Plus className="size-4" /> New category
                                </Button>
                            }
                        />
                    </div>
                </div>

                <QuizManager
                    sectionId={section.id}
                    quiz={section.quiz ?? null}
                />

                {list.length === 0 ? (
                    <Card className="flex flex-col items-center justify-center gap-3 border-dashed p-12 text-center">
                        <Layers className="size-10 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            No categories yet. Add a category to start building
                            this station&apos;s checklist.
                        </p>
                    </Card>
                ) : (
                    <BuilderSelectionProvider value={{ isSelected, toggle }}>
                        <div className="grid gap-3 pb-16">
                            {list.map((category) => (
                                <CategoryBlock
                                    key={category.id}
                                    category={category}
                                    sectionId={section.id}
                                    dragProps={itemProps(category.id)}
                                />
                            ))}
                        </div>
                    </BuilderSelectionProvider>
                )}
            </div>

            <BulkActionBar
                selectedCategoryIds={[...selectedCategories]}
                selectedItemIds={[...selectedItems]}
                moveTargets={moveTargets}
                onClear={clearSelection}
            />
        </>
    );
}

BuilderSection.layout = (page: { section: Section }) => ({
    breadcrumbs: [
        { title: 'Content builder', href: index() },
        { title: page.section.title, href: edit(page.section.id) },
    ] satisfies BreadcrumbItem[],
});
