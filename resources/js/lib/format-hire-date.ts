/**
 * "May 27, 2026" from a plain Y-m-d date. Parsed as a local date so the day
 * never shifts the way `new Date('2026-05-27')` (UTC) can.
 */
export function formatHireDate(date: string): string {
    return new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}
