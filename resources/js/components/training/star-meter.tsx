import { Star } from 'lucide-react';
import { cn } from '@/lib/utils';

const STARS = [0, 1, 2, 3, 4];

/**
 * When a station/skill counts as a Development Need — mirrors
 * StationAssessment::developmentNeeds() on the server.
 */
export const DEVELOPMENT_NEED_HINT =
    'Rated under 3★ — or, when nothing is under 3★, the lowest-rated station (unless all are 5★).';

/** "2.75" → "2¾" — how a quarter-star rating reads in text. */
export function formatStars(value: number): string {
    const whole = Math.floor(value);
    const fraction = value - whole;
    const glyph =
        fraction >= 0.75
            ? '¾'
            : fraction >= 0.5
              ? '½'
              : fraction >= 0.25
                ? '¼'
                : '';

    return whole === 0 && glyph ? glyph : `${whole}${glyph}`;
}

/**
 * A read-only 0–5 star rating that can show partial stars (¼, ½, ¾) — used
 * for station assessment results. For picking whole stars, see StarRating.
 */
export function StarMeter({
    value,
    size = 'md',
    showValue = false,
    className,
}: {
    value: number;
    size?: 'sm' | 'md' | 'lg';
    showValue?: boolean;
    className?: string;
}) {
    const starClass =
        size === 'sm' ? 'size-3.5' : size === 'lg' ? 'size-6' : 'size-4.5';
    const clamped = Math.min(Math.max(value, 0), 5);

    return (
        <span
            className={cn('inline-flex items-center gap-1.5', className)}
            role="img"
            aria-label={`${clamped} out of 5 stars`}
        >
            <span className="inline-flex items-center gap-0.5">
                {STARS.map((index) => {
                    const fill = Math.min(Math.max(clamped - index, 0), 1);

                    return (
                        <span key={index} className="relative inline-flex">
                            <Star
                                className={cn(
                                    starClass,
                                    'fill-transparent text-muted-foreground/40',
                                )}
                            />
                            {fill > 0 && (
                                <span
                                    className="absolute inset-0 overflow-hidden"
                                    style={{ width: `${fill * 100}%` }}
                                >
                                    <Star
                                        className={cn(
                                            starClass,
                                            'fill-amber-400 text-amber-400',
                                        )}
                                    />
                                </span>
                            )}
                        </span>
                    );
                })}
            </span>
            {showValue && (
                <span className="text-sm font-medium tabular-nums">
                    {formatStars(clamped)}
                </span>
            )}
        </span>
    );
}
