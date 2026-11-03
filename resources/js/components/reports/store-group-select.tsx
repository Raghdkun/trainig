import { Check, ChevronDown, Store } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import type { StoreOption } from '@/types/training';

/**
 * Pick one store, several stores, or all of them, to see their combined
 * report. Ticking boxes only changes a draft — the report reloads once, when
 * Apply is pressed. An empty selection means "All stores".
 */
export function StoreGroupSelect({
    options,
    value,
    onApply,
}: {
    options: StoreOption[];
    value: number[];
    onApply: (storeIds: number[]) => void;
}) {
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState<number[]>(value);

    const selected = options.filter((store) => value.includes(store.id));
    const label =
        selected.length === 0
            ? 'All stores'
            : selected.length === 1
              ? selected[0].name
              : `${selected.length} stores`;
    const everyStoreTicked = draft.length === options.length;

    function onOpenChange(next: boolean) {
        if (next) {
            setDraft(value);
        }

        setOpen(next);
    }

    function toggle(storeId: number, checked: boolean) {
        setDraft((current) =>
            checked
                ? [...current, storeId]
                : current.filter((id) => id !== storeId),
        );
    }

    function apply(storeIds: number[]) {
        // Ticking every store is the same as "All stores".
        onApply(storeIds.length === options.length ? [] : storeIds);
        setOpen(false);
    }

    return (
        <Popover open={open} onOpenChange={onOpenChange}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    className="w-full justify-between gap-2 font-normal sm:w-48"
                    aria-label="Choose stores"
                    title={selected.map((store) => store.name).join(', ')}
                >
                    <span className="flex min-w-0 items-center gap-2">
                        <Store className="size-4 shrink-0 text-muted-foreground" />
                        <span className="truncate">{label}</span>
                    </span>
                    <ChevronDown className="size-4 shrink-0 text-muted-foreground" />
                </Button>
            </PopoverTrigger>
            <PopoverContent align="start" className="w-72 p-0">
                <div className="flex items-center justify-between gap-2 border-b border-border/60 px-3 py-2.5">
                    <p className="text-sm font-medium">Compare stores</p>
                    <button
                        type="button"
                        className="text-xs font-medium text-muted-foreground hover:text-foreground"
                        onClick={() =>
                            setDraft(
                                everyStoreTicked
                                    ? []
                                    : options.map((store) => store.id),
                            )
                        }
                    >
                        {everyStoreTicked ? 'Clear all' : 'Select all'}
                    </button>
                </div>

                <div className="max-h-64 overflow-y-auto p-1.5">
                    <button
                        type="button"
                        onClick={() => setDraft([])}
                        className={cn(
                            'flex w-full items-center gap-2.5 rounded-md px-2 py-2 text-left text-sm hover:bg-muted/60',
                            draft.length === 0 && 'font-medium',
                        )}
                    >
                        <span className="flex size-4 items-center justify-center">
                            {draft.length === 0 && (
                                <Check className="size-4 text-primary" />
                            )}
                        </span>
                        All stores
                    </button>
                    {options.map((store) => {
                        const checked = draft.includes(store.id);
                        const id = `store-group-${store.id}`;

                        return (
                            <Label
                                key={store.id}
                                htmlFor={id}
                                className={cn(
                                    'flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-2 text-sm font-normal hover:bg-muted/60',
                                    checked && 'font-medium',
                                )}
                            >
                                <Checkbox
                                    id={id}
                                    checked={checked}
                                    onCheckedChange={(next) =>
                                        toggle(store.id, next === true)
                                    }
                                />
                                <span className="truncate">{store.name}</span>
                            </Label>
                        );
                    })}
                </div>

                <div className="flex items-center justify-between gap-2 border-t border-border/60 px-3 py-2.5">
                    <span className="text-xs text-muted-foreground">
                        {draft.length === 0
                            ? 'Showing all stores'
                            : `${draft.length} selected`}
                    </span>
                    <Button size="sm" onClick={() => apply(draft)}>
                        Apply
                    </Button>
                </div>
            </PopoverContent>
        </Popover>
    );
}
