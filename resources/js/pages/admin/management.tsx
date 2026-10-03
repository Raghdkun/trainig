import { Head, usePage } from '@inertiajs/react';
import { StoreManagement } from '@/components/dashboard/store-management';
import { UserManagement } from '@/components/dashboard/user-management';
import Heading from '@/components/heading';
import { management } from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';
import type { Paginated } from '@/types/pagination';
import type {
    AdminStoreRow,
    AdminUserRow,
    PermissionOption,
    RoleOption,
    StoreOption,
} from '@/types/training';

type ManagementProps = {
    currentUserId: number;
    users: Paginated<AdminUserRow>;
    stores: Paginated<AdminStoreRow>;
    storeOptions: StoreOption[];
    roleOptions: RoleOption[];
    permissionOptions: PermissionOption[];
};

export default function Management() {
    const {
        currentUserId,
        users,
        stores,
        storeOptions,
        roleOptions,
        permissionOptions,
    } = usePage<ManagementProps>().props;

    return (
        <>
            <Head title="Management" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="Management"
                    description="Manage your team and stores, and control who has access to what."
                />

                <UserManagement
                    users={users}
                    stores={storeOptions}
                    roleOptions={roleOptions}
                    permissionOptions={permissionOptions}
                    currentUserId={currentUserId}
                />

                <StoreManagement stores={stores} />
            </div>
        </>
    );
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Management', href: management() },
];

Management.layout = { breadcrumbs };
