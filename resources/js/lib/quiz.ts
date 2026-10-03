/** A score at or above this counts as a pass in Quiz Results. */
export const PASSING_SCORE = 70;

export const OPTION_LETTERS = ['A', 'B', 'C', 'D'];

export function isPassingScore(score: number | null): boolean {
    return score !== null && score >= PASSING_SCORE;
}

const RELATIVE_UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 60 * 60 * 24 * 365],
    ['month', 60 * 60 * 24 * 30],
    ['week', 60 * 60 * 24 * 7],
    ['day', 60 * 60 * 24],
    ['hour', 60 * 60],
    ['minute', 60],
];

const relativeFormatter = new Intl.RelativeTimeFormat(undefined, {
    numeric: 'auto',
});

/** "3 hours ago", "yesterday", "just now". */
export function formatRelativeTime(iso: string): string {
    const seconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000);

    for (const [unit, unitSeconds] of RELATIVE_UNITS) {
        if (Math.abs(seconds) >= unitSeconds) {
            return relativeFormatter.format(
                Math.round(seconds / unitSeconds),
                unit,
            );
        }
    }

    return 'just now';
}

/** "Sep 28, 2026, 12:15 PM" — for tooltips and detail timelines. */
export function formatDateTime(iso: string): string {
    return new Date(iso).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
