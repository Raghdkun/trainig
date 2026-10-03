<?php

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_managers_cannot_manage_users(): void
    {
        $manager = User::factory()->manager()->create();
        $target = User::factory()->manager()->create();

        $this->actingAs($manager)->post(route('admin.users.store'), [])->assertForbidden();
        $this->actingAs($manager)->patch(route('admin.users.update', $target), [
            'role' => 'super_admin',
        ])->assertForbidden();
    }

    public function test_super_admin_can_create_a_manager(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $store = Store::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Casey Nolan',
            'email' => 'casey@example.com',
            'password' => 'secret-pw-123',
            'role' => 'manager',
            'store_ids' => [$store->id],
        ])->assertSessionHasNoErrors();

        $user = User::firstWhere('email', 'casey@example.com');
        $this->assertNotNull($user);
        $this->assertSame(Role::Manager, $user->role);
        $this->assertTrue($user->stores->pluck('id')->contains($store->id));
    }

    public function test_super_admin_can_assign_a_manager_to_multiple_stores(): void
    {
        $admin = User::factory()->superAdmin()->create();
        [$storeA, $storeB] = Store::factory()->count(2)->create();
        $manager = User::factory()->manager($storeA)->create();

        $this->actingAs($admin)->patch(route('admin.users.update', $manager), [
            'role' => 'manager',
            'store_ids' => [$storeA->id, $storeB->id],
        ])->assertSessionHasNoErrors();

        $ids = $manager->refresh()->stores->pluck('id');
        $this->assertCount(2, $ids);
        $this->assertTrue($ids->contains($storeA->id));
        $this->assertTrue($ids->contains($storeB->id));
    }

    public function test_creating_a_manager_requires_a_store(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'No Store',
            'email' => 'nostore@example.com',
            'password' => 'secret-pw-123',
            'role' => 'manager',
        ])->assertSessionHasErrors('store_ids');
    }

    public function test_promoting_to_super_admin_clears_the_stores(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();

        $this->actingAs($admin)->patch(route('admin.users.update', $manager), [
            'role' => 'super_admin',
        ])->assertSessionHasNoErrors();

        $manager->refresh();
        $this->assertSame(Role::SuperAdmin, $manager->role);
        $this->assertCount(0, $manager->stores);
    }

    public function test_super_admin_cannot_change_their_own_role(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->patch(route('admin.users.update', $admin), [
            'role' => 'manager',
            'store_ids' => [Store::factory()->create()->id],
        ])->assertSessionHasErrors('role');

        $this->assertSame(Role::SuperAdmin, $admin->refresh()->role);
    }

    public function test_super_admin_cannot_delete_themselves(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_super_admin_can_delete_another_user(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $other = User::factory()->manager()->create();

        $this->actingAs($admin)->delete(route('admin.users.destroy', $other))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('users', ['id' => $other->id]);
    }

    // --- Permissions -------------------------------------------------------

    public function test_managers_have_no_extra_permissions_by_default(): void
    {
        $manager = User::factory()->manager()->create();

        $this->assertFalse($manager->hasPermission(Permission::ShareQuizLinks));
    }

    public function test_super_admins_hold_every_permission(): void
    {
        $admin = User::factory()->superAdmin()->create();

        foreach (Permission::cases() as $permission) {
            $this->assertTrue($admin->hasPermission($permission));
        }
    }

    public function test_super_admin_can_create_a_manager_with_a_permission(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $store = Store::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Rafael Okafor',
            'email' => 'rafael@example.com',
            'password' => 'secret-pw-123',
            'role' => 'manager',
            'store_ids' => [$store->id],
            'permissions' => ['share_quiz_links'],
        ])->assertSessionHasNoErrors();

        $this->assertTrue(User::firstWhere('email', 'rafael@example.com')->hasPermission(Permission::ShareQuizLinks));
    }

    public function test_super_admin_can_grant_and_revoke_a_managers_permission(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();

        $this->actingAs($admin)->patch(route('admin.users.update', $manager), [
            'role' => 'manager',
            'store_ids' => [$store->id],
            'permissions' => ['share_quiz_links'],
        ])->assertSessionHasNoErrors();

        $this->assertTrue($manager->refresh()->hasPermission(Permission::ShareQuizLinks));

        $this->actingAs($admin)->patch(route('admin.users.update', $manager), [
            'role' => 'manager',
            'store_ids' => [$store->id],
            'permissions' => [],
        ])->assertSessionHasNoErrors();

        $this->assertFalse($manager->refresh()->hasPermission(Permission::ShareQuizLinks));
    }

    public function test_changing_a_managers_stores_keeps_their_permissions(): void
    {
        $admin = User::factory()->superAdmin()->create();
        [$storeA, $storeB] = Store::factory()->count(2)->create();
        $manager = User::factory()->manager($storeA)->withPermissions(Permission::ShareQuizLinks)->create();

        $this->actingAs($admin)->patch(route('admin.users.update', $manager), [
            'role' => 'manager',
            'store_ids' => [$storeA->id, $storeB->id],
        ])->assertSessionHasNoErrors();

        $this->assertTrue($manager->refresh()->hasPermission(Permission::ShareQuizLinks));
    }

    public function test_promoting_to_super_admin_clears_stored_permissions(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $manager = User::factory()->manager()->withPermissions(Permission::ShareQuizLinks)->create();

        $this->actingAs($admin)->patch(route('admin.users.update', $manager), [
            'role' => 'super_admin',
        ])->assertSessionHasNoErrors();

        $this->assertNull($manager->refresh()->permissions);
    }

    public function test_demoting_a_super_admin_does_not_carry_permissions_over(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $otherAdmin = User::factory()->superAdmin()->create();
        $store = Store::factory()->create();

        $this->actingAs($admin)->patch(route('admin.users.update', $otherAdmin), [
            'role' => 'manager',
            'store_ids' => [$store->id],
        ])->assertSessionHasNoErrors();

        $this->assertFalse($otherAdmin->refresh()->hasPermission(Permission::ShareQuizLinks));
    }

    public function test_an_unknown_permission_is_rejected(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();

        $this->actingAs($admin)->patch(route('admin.users.update', $manager), [
            'role' => 'manager',
            'store_ids' => [$store->id],
            'permissions' => ['delete_everything'],
        ])->assertSessionHasErrors('permissions.0');

        $this->assertNull($manager->refresh()->permissions);
    }

    public function test_managers_cannot_grant_themselves_a_permission(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();

        $this->actingAs($manager)->patch(route('admin.users.update', $manager), [
            'role' => 'manager',
            'store_ids' => [$store->id],
            'permissions' => ['share_quiz_links'],
        ])->assertForbidden();

        $this->assertFalse($manager->refresh()->hasPermission(Permission::ShareQuizLinks));
    }
}
