import { router, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown, Globe } from 'lucide-react';
import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { useStoreFilter } from '@/hooks/use-store-filter';
import { cn } from '@/lib/utils';
import type { StoreSwitcherContext } from '@/types/training';

/** Inertia page components whose data actually changes with the store filter. */
const STORE_AWARE_PAGES = new Set([
    'dashboard',
    'training/trainees/index',
    'training/development-zone/index',
    'reports/index',
]);

export function StoreSwitcher() {
    const page = usePage<{ storeSwitcher: StoreSwitcherContext | null }>();
    const { storeSwitcher } = page.props;
    const { selectedStoreId, setSelectedStoreId } = useStoreFilter();
    const [open, setOpen] = useState(false);

    if (!storeSwitcher?.canChoose) {
        return null;
    }

    const current = storeSwitcher.options.find(
        (store) => store.id === selectedStoreId,
    );

    function selectStore(storeId: number | null) {
        setSelectedStoreId(storeId);
        setOpen(false);

        if (!STORE_AWARE_PAGES.has(page.component)) {
            return;
        }

        const url = new URL(window.location.href);

        if (storeId) {
            url.searchParams.set('store', String(storeId));
        } else {
            url.searchParams.delete('store');
        }

        router.get(
            url.pathname + url.search,
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return (
        <SidebarMenuItem>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogTrigger asChild>
                    <SidebarMenuButton tooltip="Switch store">
                        <Building2 />
                        <span className="truncate">
                            {current ? current.name : 'All stores'}
                        </span>
                        <ChevronsUpDown className="ml-auto size-3.5 shrink-0 text-muted-foreground" />
                    </SidebarMenuButton>
                </DialogTrigger>
                <DialogContent className="sm:max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Select store</DialogTitle>
                        <DialogDescription>
                            Your selection follows you across the dashboard,
                            trainees, and reports.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="flex max-h-80 flex-col gap-1 overflow-y-auto">
                        <StoreOptionRow
                            label="All stores"
                            icon={Globe}
                            active={selectedStoreId === null}
                            onClick={() => selectStore(null)}
                        />
                        {storeSwitcher.options.map((store) => (
                            <StoreOptionRow
                                key={store.id}
                                label={store.name}
                                active={selectedStoreId === store.id}
                                onClick={() => selectStore(store.id)}
                            />
                        ))}
                    </div>
                </DialogContent>
            </Dialog>
        </SidebarMenuItem>
    );
}

function StoreOptionRow({
    label,
    icon: Icon,
    active,
    onClick,
}: {
    label: string;
    icon?: typeof Globe;
    active: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'flex items-center gap-2 rounded-md border p-2 text-left text-sm transition-colors hover:bg-muted/50',
                active && 'border-primary/50 bg-muted',
            )}
        >
            {Icon && <Icon className="size-4 shrink-0 text-muted-foreground" />}
            <span className="truncate">{label}</span>
            {active && <Check className="ml-auto size-4 shrink-0" />}
        </button>
    );
}
