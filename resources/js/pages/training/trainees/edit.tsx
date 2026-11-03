import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Save, Store as StoreIcon } from 'lucide-react';
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
import { edit, index, show, update } from '@/routes/trainees';
import type { BreadcrumbItem } from '@/types';
import type { PositionOption, StoreOption } from '@/types/training';

type EditableTrainee = {
    id: number;
    name: string;
    position: string | null;
    /** "2026-09-28", or null when no hire date is on file. */
    hired_at: string | null;
    store_id: number;
};

/**
 * Same layout as Add trainee, minus the required-field rules: every detail
 * can be left as it is, and only what changes is saved.
 */
export default function TraineeEdit() {
    const { trainee, stores, canChooseStore, positionOptions } = usePage<{
        trainee: EditableTrainee;
        stores: StoreOption[];
        canChooseStore: boolean;
        positionOptions: PositionOption[];
    }>().props;

    const form = useForm({
        name: trainee.name,
        position: trainee.position ?? '',
        hired_at: trainee.hired_at ?? '',
        store_id: String(trainee.store_id),
    });

    // A single-store manager can't move the trainee, so just say where they are.
    const currentStore = canChooseStore
        ? null
        : (stores.find((store) => store.id === trainee.store_id) ?? null);

    function submit(event: FormEvent) {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        form.transform((data) => ({
            ...data,
            position: data.position === '' ? null : data.position,
            hired_at: data.hired_at === '' ? null : data.hired_at,
        }));
        form.put(update(trainee.id).url);
    }

    return (
        <>
            <Head title={`Edit ${trainee.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Edit trainee"
                    description="Update this crew member's details."
                />

                <Card className="max-w-2xl gap-0 p-0">
                    <form onSubmit={submit} noValidate>
                        <div className="grid gap-5 p-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Full name</Label>
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
                                />
                                <InputError message={form.errors.name} />
                            </div>

                            <div className="grid gap-5 sm:grid-cols-2">
                                <div className="grid content-start gap-2">
                                    <Label htmlFor="position">Position</Label>
                                    <Select
                                        name="position"
                                        value={form.data.position}
                                        onValueChange={(value) =>
                                            form.setData('position', value)
                                        }
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
                                    <Label htmlFor="hired_at">Hire date</Label>
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
                                    <Label htmlFor="store_id">Store</Label>
                                    <Select
                                        name="store_id"
                                        value={form.data.store_id}
                                        onValueChange={(value) =>
                                            form.setData('store_id', value)
                                        }
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

                            {currentStore && (
                                <p className="flex items-center gap-2 rounded-md bg-muted/50 px-3 py-2 text-sm text-muted-foreground">
                                    <StoreIcon className="size-4 shrink-0" />
                                    <span>
                                        Works at{' '}
                                        <span className="font-medium text-foreground">
                                            {currentStore.name}
                                        </span>
                                    </span>
                                </p>
                            )}

                            {/* Outside the picker: the store error can fire while
                                the picker itself is hidden. */}
                            <InputError message={form.errors.store_id} />
                        </div>

                        <footer className="flex flex-col-reverse gap-3 border-t border-border/60 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <p
                                className="text-xs text-muted-foreground"
                                aria-live="polite"
                            >
                                {form.isDirty
                                    ? 'You have unsaved changes.'
                                    : 'No changes yet.'}
                            </p>
                            <div className="flex gap-2 sm:justify-end">
                                <Button
                                    variant="ghost"
                                    className="flex-1 sm:flex-none"
                                    asChild
                                >
                                    <Link href={show(trainee.id).url}>
                                        Cancel
                                    </Link>
                                </Button>
                                <Button
                                    type="submit"
                                    className="flex-1 sm:flex-none"
                                    disabled={form.processing}
                                >
                                    {form.processing ? (
                                        <Spinner />
                                    ) : (
                                        <Save className="size-4" />
                                    )}
                                    Save changes
                                </Button>
                            </div>
                        </footer>
                    </form>
                </Card>
            </div>
        </>
    );
}

TraineeEdit.layout = (page: { trainee: EditableTrainee }) => ({
    breadcrumbs: [
        { title: 'Trainees', href: index() },
        { title: page.trainee.name, href: show(page.trainee.id) },
        { title: 'Edit', href: edit(page.trainee.id) },
    ] satisfies BreadcrumbItem[],
});
