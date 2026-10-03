import { useForm } from '@inertiajs/react';
import { ChevronDown, Search, Target, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { update } from '@/routes/trainees/development';
import type { DevelopmentPickerSection } from '@/types/training';

function matches(text: string, query: string) {
    return text.toLowerCase().includes(query.toLowerCase());
}

export function DevelopmentPlanPicker({
    traineeId,
    sections,
    selectedIds,
    trigger,
}: {
    traineeId: number;
    sections: DevelopmentPickerSection[];
    selectedIds: number[];
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [openSectionIds, setOpenSectionIds] = useState<Set<number>>(
        () => new Set(),
    );
    const form = useForm<{ item_ids: number[] }>({ item_ids: selectedIds });

    // Reset to the currently-saved selection each time the dialog opens, so
    // a cancelled edit doesn't leave stale picks for next time.
    function onOpenChange(next: boolean) {
        if (next) {
            form.setData('item_ids', selectedIds);
            setQuery('');
        }

        setOpen(next);
    }

    function toggle(itemId: number, checked: boolean) {
        form.setData(
            'item_ids',
            checked
                ? [...form.data.item_ids, itemId]
                : form.data.item_ids.filter((id) => id !== itemId),
        );
    }

    function toggleSection(section: DevelopmentPickerSection, select: boolean) {
        const ids = section.categories.flatMap((category) =>
            category.items.map((item) => item.id),
        );

        form.setData(
            'item_ids',
            select
                ? [...new Set([...form.data.item_ids, ...ids])]
                : form.data.item_ids.filter((id) => !ids.includes(id)),
        );
    }

    function toggleSectionOpen(sectionId: number) {
        setOpenSectionIds((prev) => {
            const next = new Set(prev);

            if (next.has(sectionId)) {
                next.delete(sectionId);
            } else {
                next.add(sectionId);
            }

            return next;
        });
    }

    // A flat lookup so the "Selected" panel can show each picked item with
    // its station/category context without walking the tree again.
    const itemMeta = useMemo(() => {
        const map = new Map<
            number,
            { title: string; sectionTitle: string; categoryTitle: string }
        >();

        for (const section of sections) {
            for (const category of section.categories) {
                for (const item of category.items) {
                    map.set(item.id, {
                        title: item.title,
                        sectionTitle: section.title,
                        categoryTitle: category.title,
                    });
                }
            }
        }

        return map;
    }, [sections]);

    const trimmedQuery = query.trim();
    const searching = trimmedQuery.length > 0;

    const filteredSections = useMemo(() => {
        return sections
            .map((section) => {
                const sectionMatches =
                    !trimmedQuery || matches(section.title, trimmedQuery);

                const categories = section.categories
                    .map((category) => {
                        const categoryMatches =
                            sectionMatches ||
                            matches(category.title, trimmedQuery);

                        const items = categoryMatches
                            ? category.items
                            : category.items.filter((item) =>
                                  matches(item.title, trimmedQuery),
                              );

                        return { ...category, items };
                    })
                    .filter((category) => category.items.length > 0);

                return { ...section, categories };
            })
            .filter((section) => section.categories.length > 0);
    }, [sections, trimmedQuery]);

    const selectedItems = form.data.item_ids
        .map((id) => {
            const meta = itemMeta.get(id);

            return meta ? { id, ...meta } : null;
        })
        .filter((item) => item !== null);

    function submit(event: FormEvent) {
        event.preventDefault();
        form.put(update(traineeId).url, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="flex max-h-[85vh] flex-col sm:max-w-2xl lg:max-w-4xl">
                <DialogHeader>
                    <DialogTitle className="text-xl">
                        Edit development plan
                    </DialogTitle>
                    <DialogDescription className="text-base">
                        Choose the existing stations, categories, and items this
                        person should focus on.
                    </DialogDescription>
                </DialogHeader>
                <form
                    onSubmit={submit}
                    className="flex min-h-0 flex-1 flex-col gap-3"
                >
                    <div className="relative shrink-0">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Search stations, categories, or items…"
                            className="pl-9"
                        />
                    </div>

                    <div className="flex min-h-0 flex-1 flex-col gap-3 lg:flex-row">
                        <div className="min-h-0 flex-1 space-y-2 overflow-y-auto rounded-lg border bg-muted/20 p-2">
                            {filteredSections.length === 0 ? (
                                <p className="p-6 text-center text-sm text-muted-foreground">
                                    Nothing matches "{trimmedQuery}".
                                </p>
                            ) : (
                                filteredSections.map((section) => {
                                    const sectionItemIds =
                                        section.categories.flatMap((category) =>
                                            category.items.map(
                                                (item) => item.id,
                                            ),
                                        );
                                    const selectedCount = sectionItemIds.filter(
                                        (id) => form.data.item_ids.includes(id),
                                    ).length;
                                    const allSelected =
                                        sectionItemIds.length > 0 &&
                                        selectedCount === sectionItemIds.length;
                                    const isOpen =
                                        searching ||
                                        openSectionIds.has(section.id);

                                    return (
                                        <div
                                            key={section.id}
                                            className="rounded-lg border bg-card"
                                        >
                                            <Collapsible
                                                open={isOpen}
                                                onOpenChange={() =>
                                                    !searching &&
                                                    toggleSectionOpen(
                                                        section.id,
                                                    )
                                                }
                                            >
                                                <div className="flex items-center gap-1 p-1.5">
                                                    <CollapsibleTrigger className="group flex flex-1 items-center gap-3 rounded-md p-1.5 text-left hover:bg-muted/50">
                                                        <ChevronDown className="size-4 shrink-0 text-muted-foreground transition-transform group-data-[state=closed]:-rotate-90" />
                                                        <span className="min-w-0 flex-1 truncate text-base font-semibold tracking-tight">
                                                            {section.title}
                                                        </span>
                                                        <Badge
                                                            variant={
                                                                selectedCount >
                                                                0
                                                                    ? 'default'
                                                                    : 'secondary'
                                                            }
                                                        >
                                                            {selectedCount}/
                                                            {
                                                                sectionItemIds.length
                                                            }
                                                        </Badge>
                                                    </CollapsibleTrigger>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        className="shrink-0"
                                                        onClick={() =>
                                                            toggleSection(
                                                                section,
                                                                !allSelected,
                                                            )
                                                        }
                                                    >
                                                        {allSelected
                                                            ? 'Clear'
                                                            : 'Select all'}
                                                    </Button>
                                                </div>
                                                <CollapsibleContent className="space-y-3 px-3 pb-3">
                                                    {section.categories.map(
                                                        (category) => (
                                                            <div
                                                                key={
                                                                    category.id
                                                                }
                                                                className="space-y-2 border-l-2 border-border pl-3"
                                                            >
                                                                <p className="text-sm font-semibold text-foreground/80">
                                                                    {
                                                                        category.title
                                                                    }
                                                                </p>
                                                                <div className="grid gap-2 sm:grid-cols-2">
                                                                    {category.items.map(
                                                                        (
                                                                            item,
                                                                        ) => {
                                                                            const checked =
                                                                                form.data.item_ids.includes(
                                                                                    item.id,
                                                                                );

                                                                            return (
                                                                                <Label
                                                                                    key={
                                                                                        item.id
                                                                                    }
                                                                                    className={cn(
                                                                                        'flex items-center gap-2.5 rounded-md border p-2.5 text-sm font-normal transition-colors',
                                                                                        checked
                                                                                            ? 'border-primary/40 bg-primary/5'
                                                                                            : 'hover:bg-muted/50',
                                                                                    )}
                                                                                >
                                                                                    <Checkbox
                                                                                        checked={
                                                                                            checked
                                                                                        }
                                                                                        onCheckedChange={(
                                                                                            value,
                                                                                        ) =>
                                                                                            toggle(
                                                                                                item.id,
                                                                                                value ===
                                                                                                    true,
                                                                                            )
                                                                                        }
                                                                                    />
                                                                                    {
                                                                                        item.title
                                                                                    }
                                                                                </Label>
                                                                            );
                                                                        },
                                                                    )}
                                                                </div>
                                                            </div>
                                                        ),
                                                    )}
                                                </CollapsibleContent>
                                            </Collapsible>
                                        </div>
                                    );
                                })
                            )}
                        </div>

                        <div className="flex min-h-0 flex-col rounded-lg border bg-card lg:w-64 lg:shrink-0 xl:w-72">
                            <div className="flex items-center justify-between gap-2 border-b p-3">
                                <div className="flex items-center gap-2">
                                    <Target className="size-4 text-muted-foreground" />
                                    <span className="text-sm font-semibold">
                                        Selected
                                    </span>
                                    <Badge variant="secondary">
                                        {selectedItems.length}
                                    </Badge>
                                </div>
                                {selectedItems.length > 0 && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            form.setData('item_ids', [])
                                        }
                                        className="text-xs font-medium text-muted-foreground hover:text-destructive"
                                    >
                                        Clear all
                                    </button>
                                )}
                            </div>
                            {selectedItems.length === 0 ? (
                                <p className="flex-1 p-4 text-center text-sm text-muted-foreground">
                                    Nothing picked yet. Check items on the left
                                    to add them here.
                                </p>
                            ) : (
                                <ul className="min-h-0 flex-1 space-y-1.5 overflow-y-auto p-2">
                                    {selectedItems.map((item) => (
                                        <li
                                            key={item.id}
                                            className="flex items-start gap-2 rounded-md bg-muted/40 p-2 text-xs"
                                        >
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate font-medium text-foreground">
                                                    {item.title}
                                                </p>
                                                <p className="truncate text-muted-foreground">
                                                    {item.sectionTitle} ·{' '}
                                                    {item.categoryTitle}
                                                </p>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    toggle(item.id, false)
                                                }
                                                className="shrink-0 text-muted-foreground hover:text-destructive"
                                                aria-label={`Remove ${item.title}`}
                                            >
                                                <X className="size-3.5" />
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </div>

                    <DialogFooter className="shrink-0">
                        <Button type="submit" disabled={form.processing}>
                            Save plan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
