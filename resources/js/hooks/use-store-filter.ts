import { router } from '@inertiajs/react';
import { useEffect, useRef, useSyncExternalStore } from 'react';

const STORAGE_KEY = 'training:selected-store';

const listeners = new Set<() => void>();
let currentStoreId: number | null = null;

const getStoredStoreId = (): number | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    const stored = localStorage.getItem(STORAGE_KEY);

    return stored ? Number(stored) : null;
};

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

const notify = (): void => listeners.forEach((listener) => listener());

if (typeof window !== 'undefined') {
    currentStoreId = getStoredStoreId();
}

const setStoreId = (storeId: number | null): void => {
    currentStoreId = storeId;

    if (storeId) {
        localStorage.setItem(STORAGE_KEY, String(storeId));
    } else {
        localStorage.removeItem(STORAGE_KEY);
    }

    notify();
};

/**
 * The single persisted "which store am I looking at" preference, shared by
 * the sidebar switcher and every store-filterable page. Mirrors the
 * localStorage + useSyncExternalStore pattern already used by
 * `useAppearance` for theme, so the value survives navigation and reloads
 * without needing a React context provider.
 */
export function useStoreFilter() {
    const selectedStoreId = useSyncExternalStore(
        subscribe,
        () => currentStoreId,
        () => null,
    );

    return { selectedStoreId, setSelectedStoreId: setStoreId } as const;
}

/**
 * Reconciles a store-filterable page's `?store=` query param against the
 * persisted preference, once per page mount:
 *  - Landing with no explicit `store` param but a different store
 *    persisted re-navigates once to apply it, so the selection "sticks"
 *    across pages instead of resetting to All stores.
 *  - Landing with an explicit `store` param just records that as the
 *    current preference, so the sidebar switcher reflects it.
 *
 * This intentionally reads the live preference directly (not through the
 * reactive `useStoreFilter`) and only ever runs once per mount: reacting to
 * every later change would also fire while the sidebar switcher's own
 * navigation for a change made *on this same page* is still in flight —
 * racing it and reverting the switch before the new page props arrive.
 * Reading the live value directly also sidesteps the SSR hydration
 * fallback (`useStoreFilter` reports `null` on first paint since the
 * server can't see localStorage) — module state is already populated from
 * localStorage before this effect ever runs.
 */
export function useSyncStoreFilter(pageStoreId: number | null): void {
    const handled = useRef(false);

    useEffect(() => {
        if (handled.current) {
            return;
        }

        handled.current = true;

        const selectedStoreId = currentStoreId;
        const url = new URL(window.location.href);
        const hasExplicitParam = url.searchParams.has('store');

        if (!hasExplicitParam && selectedStoreId !== pageStoreId) {
            if (selectedStoreId) {
                url.searchParams.set('store', String(selectedStoreId));
            } else {
                url.searchParams.delete('store');
            }

            router.get(
                url.pathname + url.search,
                {},
                { preserveState: true, preserveScroll: true, replace: true },
            );

            return;
        }

        if (selectedStoreId !== pageStoreId) {
            setStoreId(pageStoreId);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);
}
