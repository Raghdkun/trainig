import { router } from '@inertiajs/react';
import { Trash2, UserPlus } from 'lucide-react';
import { toast } from 'sonner';
import { InviteUserDialog } from '@/components/dashboard/invite-user-dialog';
import { PermissionChecklist } from '@/components/dashboard/permission-checklist';
import { StoreMultiSelect } from '@/components/dashboard/store-multi-select';
import { Paginator } from '@/components/pagination';
import { ConfirmDeleteDialog } from '@/components/training/confirm-delete-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { destroy, update } from '@/routes/admin/users';
import type { Paginated } from '@/types/pagination';
import type {
    AdminUserRow,
    PermissionOption,
    PermissionValue,
    RoleOption,
    RoleValue,
    StoreOption,
} from '@/types/training';

export function UserManagement({
    users,
    stores,
    roleOptions,
    permissionOptions,
    currentUserId,
}: {
    users: Paginated<AdminUserRow>;
    stores: StoreOption[];
    roleOptions: RoleOption[];
    permissionOptions: PermissionOption[];
    currentUserId: number;
}) {
    function save(
        user: AdminUserRow,
        role: RoleValue,
        storeIds: number[],
        permissions?: PermissionValue[],
    ) {
        router.patch(
            update(user.id).url,
            {
                role,
                store_ids: role === 'manager' ? storeIds : [],
                // Left out, the server keeps the user's current permissions.
                ...(permissions !== undefined && { permissions }),
            },
            {
                preserveScroll: true,
                // Without this the row silently snaps back (e.g. unchecking a
                // manager's last store) and the admin has no idea why.
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'That change could not be saved.',
                    ),
            },
        );
    }

    function changeRole(user: AdminUserRow, role: RoleValue) {
        const current = user.stores.map((store) => store.id);
        const storeIds =
            role === 'manager'
                ? current.length > 0
                    ? current
                    : stores[0]
                      ? [stores[0].id]
                      : []
                : [];
        save(user, role, storeIds);
    }

    return (
        <section className="surface-tray min-w-0">
            <div className="surface-core overflow-hidden">
                <header className="flex items-center justify-between gap-3 border-b border-border/60 p-4">
                    <div className="min-w-0">
                        <h2 className="font-semibold tracking-tight">Team</h2>
                        <p className="truncate text-sm text-muted-foreground">
                            {users.total} member{users.total === 1 ? '' : 's'} ·
                            roles, store access &amp; permissions
                        </p>
                    </div>
                    <InviteUserDialog
                        roleOptions={roleOptions}
                        permissionOptions={permissionOptions}
                        stores={stores}
                        trigger={
                            <Button size="sm" className="shrink-0">
                                <UserPlus className="size-4" />
                                <span className="hidden sm:inline">Invite</span>
                            </Button>
                        }
                    />
                </header>

                <ul className="divide-y divide-border/60">
                    {users.data.map((user) => {
                        const isSelf = user.id === currentUserId;

                        return (
                            <li
                                key={user.id}
                                className="grid gap-3 p-4 xl:grid-cols-[minmax(0,1fr)_9rem_11rem_10rem_auto] xl:items-center xl:gap-4"
                            >
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2">
                                        <span className="truncate font-medium">
                                            {user.name}
                                        </span>
                                        {isSelf && (
                                            <Badge variant="secondary">
                                                You
                                            </Badge>
                                        )}
                                    </div>
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {user.email}
                                    </span>
                                </div>

                                <div className="grid grid-cols-2 gap-2 xl:contents">
                                    <Select
                                        value={user.role}
                                        disabled={isSelf}
                                        onValueChange={(value) =>
                                            changeRole(user, value as RoleValue)
                                        }
                                    >
                                        <SelectTrigger
                                            size="sm"
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

                                    {user.role === 'manager' ? (
                                        <StoreMultiSelect
                                            value={user.stores.map(
                                                (store) => store.id,
                                            )}
                                            options={stores}
                                            onChange={(ids) =>
                                                save(user, 'manager', ids)
                                            }
                                            className="text-sm"
                                        />
                                    ) : (
                                        <span className="flex items-center text-sm text-muted-foreground">
                                            All stores
                                        </span>
                                    )}
                                </div>

                                {/* Stacked: permissions and delete share a line. */}
                                <div className="flex items-center justify-between gap-3 xl:contents">
                                    {user.role === 'manager' ? (
                                        <PermissionChecklist
                                            compact
                                            idPrefix={`user-${user.id}-permission`}
                                            options={permissionOptions}
                                            value={user.permissions}
                                            onChange={(permissions) =>
                                                save(
                                                    user,
                                                    'manager',
                                                    user.stores.map(
                                                        (store) => store.id,
                                                    ),
                                                    permissions,
                                                )
                                            }
                                        />
                                    ) : (
                                        <span className="text-sm text-muted-foreground">
                                            All permissions
                                        </span>
                                    )}

                                    <div className="flex justify-end">
                                        {!isSelf && (
                                            <ConfirmDeleteDialog
                                                title="Remove user?"
                                                description={`This permanently deletes ${user.name}'s account.`}
                                                onConfirm={(close) =>
                                                    router.delete(
                                                        destroy(user.id).url,
                                                        {
                                                            preserveScroll: true,
                                                            onSuccess: close,
                                                        },
                                                    )
                                                }
                                                trigger={
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-muted-foreground hover:text-destructive"
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                }
                                            />
                                        )}
                                    </div>
                                </div>
                            </li>
                        );
                    })}
                </ul>

                {users.last_page > 1 && (
                    <div className="border-t border-border/60 p-4">
                        <Paginator
                            paginator={users}
                            only="users"
                            label="users"
                        />
                    </div>
                )}
            </div>
        </section>
    );
}
