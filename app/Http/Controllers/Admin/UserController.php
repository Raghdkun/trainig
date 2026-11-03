<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class UserController extends Controller
{
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $role = Role::from($request->validated('role'));

        $user = User::forceCreate([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
            'role' => $role,
            'permissions' => $this->grantablePermissions($role, $request->validated('permissions', [])),
            'email_verified_at' => now(),
        ]);

        $user->stores()->sync($role === Role::Manager ? $request->validated('store_ids', []) : []);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return back();
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        // Guard against locking yourself out of the super admin role.
        if ($user->is($request->user()) && $request->validated('role') !== $user->role->value) {
            throw ValidationException::withMessages(['role' => __('You cannot change your own role.')]);
        }

        $role = Role::from($request->validated('role'));
        $user->role = $role;

        if ($role !== Role::Manager) {
            $user->permissions = null;
        } elseif ($request->has('permissions')) {
            $user->permissions = $this->grantablePermissions($role, $request->validated('permissions', []));
        }

        $user->save();

        $user->stores()->sync($role === Role::Manager ? $request->validated('store_ids', []) : []);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return back();
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->is(request()->user())) {
            throw ValidationException::withMessages(['user' => __('You cannot delete your own account.')]);
        }

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User removed.')]);

        return back();
    }

    /**
     * Only managers carry granted permissions; super admins already hold
     * every permission, so nothing is stored for them.
     *
     * @param  array<int, string>  $values
     * @return list<Permission>|null
     */
    private function grantablePermissions(Role $role, array $values): ?array
    {
        if ($role !== Role::Manager) {
            return null;
        }

        return array_values(array_map(fn (string $value): Permission => Permission::from($value), $values));
    }
}
