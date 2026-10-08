import { router } from '@inertiajs/react';
import { GripVertical, Pencil, Plus, Trash2 } from 'lucide-react';
import { useBuilderSelection } from '@/components/training/builder-selection';
import { ConfirmDeleteDialog } from '@/components/training/confirm-delete-dialog';
import { ImportanceBadge } from '@/components/training/importance-badge';
import { ItemFormDialog } from '@/components/training/item-form-dialog';
import { MediaManager } from '@/components/training/media-manager';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { useSortable } from '@/hooks/use-sortable';
import type { SortableHandleProps } from '@/hooks/use-sortable';
import { cn } from '@/lib/utils';
import { destroy, reorder } from '@/routes/training/items';
import type { ChecklistItem } from '@/types/training';

export function ChecklistItemRow({
    item,
    categoryId,
    dragProps,
    isSub = false,
}: {
    item: ChecklistItem;
    categoryId: number;
    dragProps: SortableHandleProps;
    isSub?: boolean;
}) {
    const children = item.children ?? [];
    const selection = useBuilderSelection();
    const { list: childList, itemProps: childProps } = useSortable(
        children,
        (payload) =>
            router.post(
                reorder(categoryId).url,
                { items: payload },
                { preserveScroll: true },
            ),
    );

    return (
        <div
            {...dragProps}
            className={cn(
                'rounded-md border bg-background transition-opacity data-[dragging]:opacity-50',
                isSub && 'border-dashed',
            )}
        >
            <div className="flex items-start gap-2 p-3">
                {!isSub && selection && (
                    <Checkbox
                        checked={selection.isSelected('item', item.id)}
                        onCheckedChange={() =>
                            selection.toggle('item', item.id)
                        }
                        onClick={(e) => e.stopPropagation()}
                        aria-label={`Select ${item.title}`}
                        className="mt-0.5 shrink-0"
                    />
                )}
                <GripVertical className="mt-0.5 size-4 shrink-0 cursor-grab text-muted-foreground" />

                <div className="min-w-0 flex-1 space-y-2">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-sm font-medium">
                            {item.title}
                        </span>
                        <ImportanceBadge importance={item.importance} />
                    </div>

                    {item.content && (
                        <p className="rounded bg-muted/50 px-2 py-1.5 text-sm whitespace-pre-line text-muted-foreground">
                            {item.content}
                        </p>
                    )}

                    <MediaManager item={item} />
                </div>

                <div className="flex shrink-0 items-center gap-1">
                    <ItemFormDialog
                        categoryId={categoryId}
                        item={item}
                        trigger={
                            <Button variant="ghost" size="icon">
                                <Pencil className="size-4" />
                            </Button>
                        }
                    />
                    <ConfirmDeleteDialog
                        title="Delete item?"
                        description="This permanently deletes the item, its sub-items, and media."
                        onConfirm={(close) =>
                            router.delete(destroy(item.id).url, {
                                preserveScroll: true,
                                onSuccess: close,
                            })
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
            </div>

            {!isSub && (
                <div className="space-y-2 border-t bg-muted/20 px-3 py-2 pl-9">
                    {childList.map((child) => (
                        <ChecklistItemRow
                            key={child.id}
                            item={child}
                            categoryId={categoryId}
                            dragProps={childProps(child.id)}
                            isSub
                        />
                    ))}
                    <ItemFormDialog
                        categoryId={categoryId}
                        parentId={item.id}
                        trigger={
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-7 text-xs"
                            >
                                <Plus className="size-3.5" /> Add sub-item
                            </Button>
                        }
                    />
                </div>
            )}
        </div>
    );
}
