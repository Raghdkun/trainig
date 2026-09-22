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
import { create, index, store } from '@/routes/trainees';
import type { BreadcrumbItem } from '@/types';
import type { StoreOption } from '@/types/training';

export default function TraineeCreate() {
    const { stores, canChooseStore } = usePage<{
        stores: StoreOption[];
        canChooseStore: boolean;
    }>().props;

    const form = useForm({
        name: '',
        position: '',
        hired_at: '',
        store_id: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(store().url);
    }

    return (
        <>
            <Head title="Add trainee" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Add trainee"
                    description="Create a new crew member to train."
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
                                autoFocus
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
                                placeholder="Crew Member"
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

                        {/* Outside the picker: a manager with no usable store
                            gets this error while the picker itself is hidden. */}
                        <InputError message={form.errors.store_id} />

                        <Button type="submit" disabled={form.processing}>
                            Add trainee
                        </Button>
                    </form>
                </Card>
            </div>
        </>
    );
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Trainees', href: index() },
    { title: 'Add trainee', href: create() },
];

TraineeCreate.layout = { breadcrumbs };
