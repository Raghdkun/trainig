import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
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
import { edit, index, show, update } from '@/routes/trainees';
import type { BreadcrumbItem } from '@/types';
import type { StoreOption } from '@/types/training';

type EditableTrainee = {
    id: number;
    name: string;
    position: string | null;
    hired_at: string | null;
    store_id: number;
};

export default function TraineeEdit() {
    const { trainee, stores, canChooseStore } = usePage<{
        trainee: EditableTrainee;
        stores: StoreOption[];
        canChooseStore: boolean;
    }>().props;

    const form = useForm({
        name: trainee.name,
        position: trainee.position ?? '',
        hired_at: trainee.hired_at ?? '',
        store_id: String(trainee.store_id),
    });

    function submit(event: FormEvent) {
        event.preventDefault();
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

                <Card className="max-w-xl p-6">
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                                required
                            />
                            <InputError message={form.errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="position">Position</Label>
                            <Input
                                id="position"
                                value={form.data.position}
                                onChange={(e) =>
                                    form.setData('position', e.target.value)
                                }
                            />
                            <InputError message={form.errors.position} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="hired_at">Hire date</Label>
                            <Input
                                id="hired_at"
                                type="date"
                                value={form.data.hired_at}
                                onChange={(e) =>
                                    form.setData('hired_at', e.target.value)
                                }
                            />
                            <InputError message={form.errors.hired_at} />
                        </div>

                        {canChooseStore && (
                            <div className="grid gap-2">
                                <Label htmlFor="store_id">Store</Label>
                                <Select
                                    value={form.data.store_id}
                                    onValueChange={(value) =>
                                        form.setData('store_id', value)
                                    }
                                >
                                    <SelectTrigger
                                        id="store_id"
                                        className="w-full"
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

                        {/* Outside the picker: the store error can fire while
                            the picker itself is hidden. */}
                        <InputError message={form.errors.store_id} />

                        <Button type="submit" disabled={form.processing}>
                            Save changes
                        </Button>
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
