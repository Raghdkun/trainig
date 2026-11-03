import { Checkbox } from '@/components/ui/checkbox';
import { cn } from '@/lib/utils';
import type { PermissionOption, PermissionValue } from '@/types/training';

/**
 * The extra abilities a super admin can grant a manager, one checkbox each.
 * `compact` drops the descriptions for tight table rows (the description is
 * still available as the label's tooltip).
 */
export function PermissionChecklist({
    idPrefix,
    options,
    value,
    onChange,
    disabled = false,
    compact = false,
    className,
}: {
    idPrefix: string;
    options: PermissionOption[];
    value: PermissionValue[];
    onChange: (permissions: PermissionValue[]) => void;
    disabled?: boolean;
    compact?: boolean;
    className?: string;
}) {
    function toggle(permission: PermissionValue, granted: boolean) {
        onChange(
            granted
                ? [...value.filter((p) => p !== permission), permission]
                : value.filter((p) => p !== permission),
        );
    }

    return (
        <div className={cn('grid gap-2', className)}>
            {options.map((option) => {
                const id = `${idPrefix}-${option.value}`;

                return (
                    <div key={option.value} className="flex items-start gap-2">
                        <Checkbox
                            id={id}
                            checked={value.includes(option.value)}
                            disabled={disabled}
                            onCheckedChange={(checked) =>
                                toggle(option.value, checked === true)
                            }
                            className="mt-0.5"
                        />
                        <label
                            htmlFor={id}
                            title={compact ? option.description : undefined}
                            className="grid cursor-pointer gap-0.5 text-sm leading-tight peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                        >
                            <span className="font-medium">{option.label}</span>
                            {!compact && (
                                <span className="text-xs text-muted-foreground">
                                    {option.description}
                                </span>
                            )}
                        </label>
                    </div>
                );
            })}
        </div>
    );
}
