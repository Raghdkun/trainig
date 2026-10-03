import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Store as StoreIcon, UserPlus } from 'lucide-react';
import type { FormEvent } from 'react';
import { DatePicker } from '@/components/date-picker';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { create, index, store } from '@/routes/trainees';
import type { BreadcrumbItem } from '@/types';
import type { PositionOption, StoreOption } from '@/types/training';

export default function TraineeCreate() {
    const { stores, canChooseStore, positionOptions } = usePage<{
        stores: StoreOption[];
        canChooseStore: boolean;
        positionOptions: PositionOption[];
    }>().props;

    const form = useForm({
        name: '',
        position: '',
        hired_at: '',
        store_id: '',
    });

    // A single-store manager's store is filled in for them — say which.
    const autoStore = !canChooseStore && stores.length === 1 ? stores[0] : null;

    // Every field is required; the store only counts when there's a choice.
    const missing = [
        form.data.name.trim() === '' && 'name',
        form.data.position === '' && 'position',
        form.data.hired_at === '' && 'hire date',
        canChooseStore && form.data.store_id === '' && 'store',
    ].filter((field): field is string => Boolean(field));
    const isComplete = missing.length === 0;

    function submit(event: FormEvent) {
        event.preventDefault();

        if (!isComplete || form.processing) {
            return;
        }

        form.post(store().url);
    }

    return (
        <>
            <Head title="Add trainee" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Add trainee"
                    description="Add a new crew member so you can track their training. Fields marked * are required."
                />

                <Card className="max-w-2xl gap-0 p-0">
                    <form onSubmit={submit} noValidate>
                        <div className="grid gap-5 p-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">
                                    Full name <RequiredMark />
                                </Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    placeholder="e.g. Jordan Lee"
                                    autoComplete="off"
                                    maxLength={255}
                                    aria-invalid={Boolean(form.errors.name)}
                                    autoFocus
                                    required
                                />
                                <InputError message={form.errors.name} />
                            </div>

                            <div className="grid gap-5 sm:grid-cols-2">
                                <div className="grid content-start gap-2">
                                    <Label htmlFor="position">
                                        Position <RequiredMark />
                                    </Label>
                                    <Select
                                        name="position"
                                        value={form.data.position}
                                        onValueChange={(value) =>
                                            form.setData('position', value)
                                        }
                                        required
                                    >
                                        <SelectTrigger
                                            id="position"
                                            className="w-full"
                                            aria-invalid={Boolean(
                                                form.errors.position,
                                            )}
                                        >
                                            <SelectValue placeholder="Select a position" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {positionOptions.map((option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={form.errors.position}
                                    />
                                </div>

                                <div className="grid content-start gap-2">
                                    <Label htmlFor="hired_at">
                                        Hire date <RequiredMark />
                                    </Label>
                                    <DatePicker
                                        id="hired_at"
                                        value={form.data.hired_at}
                                        onChange={(value) =>
                                            form.setData('hired_at', value)
                                        }
                                        placeholder="Select a date"
                                        invalid={Boolean(form.errors.hired_at)}
                                    />
                                    <InputError
                                        message={form.errors.hired_at}
                                    />
                                </div>
                            </div>

                            {canChooseStore && (
                                <div className="grid gap-2">
                                    <Label htmlFor="store_id">
                                        Store <RequiredMark />
                                    </Label>
                                    <Select
                                        name="store_id"
                                        value={form.data.store_id}
                                        onValueChange={(value) =>
                                            form.setData('store_id', value)
                                        }
                                        required
                                    >
                                        <SelectTrigger
                                            id="store_id"
                                            className="w-full"
                                            aria-invalid={Boolean(
                                                form.errors.store_id,
                                            )}
                                        >
                                            <SelectValue placeholder="Select a store" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {stores.map((s) => (
                                                <SelectItem
                                                    key={s.id}
                                                    value={String(s.id)}
                                                >
                                                    {s.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}

                            {autoStore && (
                                <p className="flex items-center gap-2 rounded-md bg-muted/50 px-3 py-2 text-sm text-muted-foreground">
                                    <StoreIcon className="size-4 shrink-0" />
                                    <span>
                                        Will be added to{' '}
                                        <span className="font-medium text-foreground">
                                            {autoStore.name}
                                        </span>
                                    </span>
                                </p>
                            )}

                            {/* Outside the picker: a manager with no usable store
                                gets this error while the picker itself is hidden. */}
                            <InputError message={form.errors.store_id} />
                        </div>

                        <footer className="flex flex-col-reverse gap-3 border-t border-border/60 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <p
                                className="text-xs text-muted-foreground"
                                aria-live="polite"
                            >
                                {isComplete
                                    ? 'Everything looks good.'
                                    : `Still needed: ${missing.join(', ')}.`}
                            </p>
                            <div className="flex gap-2 sm:justify-end">
                                <Button
                                    variant="ghost"
                                    className="flex-1 sm:flex-none"
                                    asChild
                                >
                                    <Link href={index().url}>Cancel</Link>
                                </Button>
                                <Button
                                    type="submit"
                                    className="flex-1 sm:flex-none"
                                    disabled={!isComplete || form.processing}
                                >
                                    {form.processing ? (
                                        <Spinner />
                                    ) : (
                                        <UserPlus className="size-4" />
                                    )}
                                    Add trainee
                                </Button>
                            </div>
                        </footer>
                    </form>
                </Card>
            </div>
        </>
    );
}

/**
 * The red "*" beside a required field's label. Hidden from screen readers,
 * which already announce "required" from the field itself.
 */
function RequiredMark() {
    return (
        <span aria-hidden className="text-destructive">
            *
        </span>
    );
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Trainees', href: index() },
    { title: 'Add trainee', href: create() },
];

TraineeCreate.layout = { breadcrumbs };
