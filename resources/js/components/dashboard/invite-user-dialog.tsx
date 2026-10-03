import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { PermissionChecklist } from '@/components/dashboard/permission-checklist';
import { StoreMultiSelect } from '@/components/dashboard/store-multi-select';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { store } from '@/routes/admin/users';
import type {
    PermissionOption,
    PermissionValue,
    RoleOption,
    RoleValue,
    StoreOption,
} from '@/types/training';

export function InviteUserDialog({
    roleOptions,
    permissionOptions,
    stores,
    trigger,
}: {
    roleOptions: RoleOption[];
    permissionOptions: PermissionOption[];
    stores: StoreOption[];
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        name: '',
        email: '',
        password: '',
        role: 'manager' as RoleValue,
        store_ids: [] as number[],
        permissions: [] as PermissionValue[],
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(store().url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Invite a team member</DialogTitle>
                    <DialogDescription>
                        Create a manager or super admin account.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="user-name">Name</Label>
                        <Input
                            id="user-name"
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
                        <Label htmlFor="user-email">Email</Label>
                        <Input
                            id="user-email"
                            type="email"
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.email} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="user-password">
                            Temporary password
                        </Label>
                        <Input
                            id="user-password"
                            type="text"
                            value={form.data.password}
                            onChange={(e) =>
                                form.setData('password', e.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.password} />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="user-role">Role</Label>
                            <Select
                                value={form.data.role}
                                onValueChange={(value) => {
                                    form.setData('role', value as RoleValue);

                                    if (value !== 'manager') {
                                        form.setData('store_ids', []);
                                        form.setData('permissions', []);
                                    }
                                }}
                            >
                                <SelectTrigger
                                    id="user-role"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {roleOptions.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.role} />
                        </div>
                        {form.data.role === 'manager' && (
                            <div className="grid gap-2">
                                <Label htmlFor="user-store">Stores</Label>
                                <StoreMultiSelect
                                    id="user-store"
                                    value={form.data.store_ids}
                                    options={stores}
                                    onChange={(ids) =>
                                        form.setData('store_ids', ids)
                                    }
                                />
                                <InputError message={form.errors.store_ids} />
                            </div>
                        )}
                    </div>
                    {form.data.role === 'manager' ? (
                        <fieldset className="grid gap-2">
                            <legend className="mb-2 text-sm font-medium">
                                Permissions
                            </legend>
                            <PermissionChecklist
                                idPrefix="new-user-permission"
                                options={permissionOptions}
                                value={form.data.permissions}
                                onChange={(permissions) =>
                                    form.setData('permissions', permissions)
                                }
                            />
                            <InputError message={form.errors.permissions} />
                        </fieldset>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Super admins have every permission.
                        </p>
                    )}
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Create account
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
