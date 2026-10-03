import { Star } from 'lucide-react';
import { cn } from '@/lib/utils';

const STARS = [1, 2, 3, 4, 5];

/**
 * A 1–5 star rating. Read-only when `onChange` is omitted (evaluation
 * summaries); interactive otherwise (the evaluation form).
 */
export function StarRating({
    value,
    onChange,
    disabled = false,
    size = 'md',
}: {
    value: number | null;
    onChange?: (value: number) => void;
    disabled?: boolean;
    size?: 'sm' | 'md';
}) {
    const readOnly = !onChange;
    const starClass = size === 'sm' ? 'size-4' : 'size-5';

    return (
        <div
            className="flex items-center gap-0.5"
            role={readOnly ? undefined : 'radiogroup'}
        >
            {STARS.map((star) => {
                const filled = value !== null && star <= value;

                return (
                    <button
                        key={star}
                        type="button"
                        disabled={disabled || readOnly}
                        onClick={() => onChange?.(star)}
                        aria-label={`${star} star${star === 1 ? '' : 's'}`}
                        aria-pressed={filled}
                        className={cn(
                            'transition-colors disabled:cursor-default',
                            readOnly
                                ? 'cursor-default'
                                : 'cursor-pointer hover:text-amber-400',
                        )}
                    >
                        <Star
                            className={cn(
                                starClass,
                                filled
                                    ? 'fill-amber-400 text-amber-400'
                                    : 'fill-transparent text-muted-foreground',
                            )}
                        />
                    </button>
                );
            })}
        </div>
    );
}
