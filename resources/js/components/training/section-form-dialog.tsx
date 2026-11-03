import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { store, update } from '@/routes/training/sections';
import type { Section } from '@/types/training';

export function SectionFormDialog({
    section,
    trigger,
}: {
    section?: Section;
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        title: section?.title ?? '',
        description: section?.description ?? '',
        icon: section?.icon ?? '',
        counts_toward_progress: section?.counts_toward_progress ?? true,
        pie_content_review: section?.pie_content_review ?? '',
        screen_to_shoulder: section?.screen_to_shoulder ?? '',
        hands_on_shifts: section?.hands_on_shifts ?? '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);

                if (!section) {
                    form.reset();
                }
            },
        };

        if (section) {
            form.put(update(section.id).url, options);
        } else {
            form.post(store().url, options);
        }
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {section ? 'Edit station' : 'New station'}
                    </DialogTitle>
                    <DialogDescription>
                        Stations are the top level of the training program.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="title">Title</Label>
                        <Input
                            id="title"
                            value={form.data.title}
                            onChange={(e) =>
                                form.setData('title', e.target.value)
                            }
                            autoFocus
                            required
                        />
                        <InputError message={form.errors.title} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="description">Description</Label>
                        <Textarea
                            id="description"
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />
                        <InputError message={form.errors.description} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="icon">Icon (lucide name)</Label>
                        <Input
                            id="icon"
                            value={form.data.icon}
                            onChange={(e) =>
                                form.setData('icon', e.target.value)
                            }
                            placeholder="Pizza"
                        />
                        <InputError message={form.errors.icon} />
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div className="grid gap-2">
                            <Label htmlFor="pie">Content review</Label>
                            <Input
                                id="pie"
                                value={form.data.pie_content_review}
                                onChange={(e) =>
                                    form.setData(
                                        'pie_content_review',
                                        e.target.value,
                                    )
                                }
                                placeholder="5 to 10 Mins"
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="sts">Screen-to-shoulder</Label>
                            <Input
                                id="sts"
                                value={form.data.screen_to_shoulder}
                                onChange={(e) =>
                                    form.setData(
                                        'screen_to_shoulder',
                                        e.target.value,
                                    )
                                }
                                placeholder="30 Mins"
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="hands">Hands-on (hours)</Label>
                            <Input
                                id="hands"
                                value={form.data.hands_on_shifts}
                                onChange={(e) =>
                                    form.setData(
                                        'hands_on_shifts',
                                        e.target.value,
                                    )
                                }
                                placeholder="2 hours"
                            />
                        </div>
                    </div>

                    <div className="flex items-start gap-3 rounded-md border p-3">
                        <Checkbox
                            id="section-counts"
                            checked={form.data.counts_toward_progress}
                            onCheckedChange={(value) =>
                                form.setData(
                                    'counts_toward_progress',
                                    value === true,
                                )
                            }
                            className="mt-0.5"
                        />
                        <div className="grid gap-1">
                            <Label
                                htmlFor="section-counts"
                                className="font-medium"
                            >
                                Counts toward evaluation
                            </Label>
                            <p className="text-xs text-muted-foreground">
                                When off, this station is optional: trainees can
                                still do it, but it is left out of completion,
                                average score and reports.
                            </p>
                            <InputError
                                message={form.errors.counts_toward_progress}
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            {section ? 'Save changes' : 'Create station'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
