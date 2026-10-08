<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ManagementController extends Controller
{
    /**
     * Team + store control center — super admins manage users and stores here.
     */
    public function index(Request $request): Response
    {
        $users = User::with('stores:id,name')
            ->orderByDesc('created_at')
            ->paginate(perPage: 10, pageName: 'users_page')
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'permissions' => $user->permissions?->map(fn (Permission $permission): string => $permission->value)->values() ?? [],
                'stores' => $user->stores->map(fn (Store $store): array => [
                    'id' => $store->id,
                    'name' => $store->name,
                ])->values(),
                'joined' => $user->created_at?->toDateString(),
            ]);

        $stores = Store::withCount(['managers', 'trainees'])
            ->orderBy('name')
            ->paginate(perPage: 10, pageName: 'stores_page')
            ->withQueryString()
            ->through(fn (Store $store): array => [
                'id' => $store->id,
                'name' => $store->name,
                'address' => $store->address,
                'managers_count' => $store->managers_count,
                'trainees_count' => $store->trainees_count,
            ]);

        return Inertia::render('admin/management', [
            'currentUserId' => $request->user()->id,
            'users' => $users,
            'stores' => $stores,
            // Un-paginated store list for the assignment dropdowns.
            'storeOptions' => Store::orderBy('name')->get(['id', 'name']),
            'roleOptions' => collect(Role::cases())->map(fn (Role $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
            ]),
            'permissionOptions' => Permission::options(),
        ]);
    }
}
