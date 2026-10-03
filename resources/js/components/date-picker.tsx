import { CalendarIcon } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

/** "2026-09-28" → a local-midnight Date (no UTC shift a day back). */
function parseDate(value: string): Date | undefined {
    const [year, month, day] = value.split('-').map(Number);

    if (!year || !month || !day) {
        return undefined;
    }

    return new Date(year, month - 1, day);
}

/** A local Date → "2026-09-28", the format Laravel's `date` rule expects. */
function toDateValue(date: Date): string {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

// A fixed locale so the server render and the browser agree on the label.
const displayFormat = new Intl.DateTimeFormat('en-US', { dateStyle: 'long' });

/**
 * A date field built on the shadcn Calendar in a Popover. Holds its value as
 * a "YYYY-MM-DD" string so it drops into `useForm` like a native date input.
 * Year/month dropdowns make jumping to an older date quick, and "Today"
 * covers the most common pick in one click.
 */
export function DatePicker({
    id,
    value,
    onChange,
    placeholder = 'Pick a date',
    invalid = false,
    fromYear = new Date().getFullYear() - 10,
    toYear = new Date().getFullYear() + 1,
}: {
    id?: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    invalid?: boolean;
    fromYear?: number;
    toYear?: number;
}) {
    const [open, setOpen] = useState(false);
    const selected = parseDate(value);

    function choose(date: Date | undefined) {
        if (!date) {
            return;
        }

        onChange(toDateValue(date));
        setOpen(false);
    }

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    aria-invalid={invalid}
                    className={cn(
                        'w-full justify-between font-normal dark:bg-input/30 dark:hover:bg-input/50',
                        !selected && 'text-muted-foreground',
                    )}
                >
                    {selected ? displayFormat.format(selected) : placeholder}
                    <CalendarIcon className="size-4 text-muted-foreground" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                className="w-auto overflow-hidden p-0"
                align="start"
            >
                <Calendar
                    mode="single"
                    selected={selected}
                    defaultMonth={selected}
                    onSelect={choose}
                    captionLayout="dropdown"
                    startMonth={new Date(fromYear, 0)}
                    endMonth={new Date(toYear, 11)}
                    autoFocus
                />
                <div className="flex items-center justify-between gap-2 border-t border-border/60 p-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => choose(new Date())}
                    >
                        Today
                    </Button>
                    {selected && (
                        <span className="pr-2 text-xs text-muted-foreground">
                            {displayFormat.format(selected)}
                        </span>
                    )}
                </div>
            </PopoverContent>
        </Popover>
    );
}
