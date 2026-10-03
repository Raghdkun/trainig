import { useSyncExternalStore } from 'react';
import { formatDateTime, formatRelativeTime } from '@/lib/quiz';

function subscribe(): () => void {
    return () => {};
}

function getClientSnapshot(): boolean {
    return true;
}

function getServerSnapshot(): boolean {
    return false;
}

/**
 * A timestamp in the viewer's own locale and timezone — "3 hours ago" or
 * "Sep 28, 2026, 12:15 PM". The SSR server can't know either (and "ago"
 * drifts between render and hydration), so the server and the first client
 * render both show the plain date, then the browser swaps in the local text.
 * That keeps hydration mismatch-free.
 */
export function LocalTime({
    iso,
    variant,
}: {
    iso: string;
    variant: 'relative' | 'datetime';
}) {
    const isClient = useSyncExternalStore(
        subscribe,
        getClientSnapshot,
        getServerSnapshot,
    );

    let text = iso.slice(0, 10);

    if (isClient) {
        text =
            variant === 'relative'
                ? formatRelativeTime(iso)
                : formatDateTime(iso);
    }

    return <time dateTime={iso}>{text}</time>;
}
