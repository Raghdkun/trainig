<?php

namespace Tests\Feature\Training;

use App\Enums\Position;
use App\Models\Store;
use App\Models\Trainee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TraineeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected(): void
    {
        $this->get(route('trainees.index'))->assertRedirect(route('login'));
    }

    public function test_create_and_edit_pages_render(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $trainee = Trainee::factory()->create();

        $this->actingAs($admin)
            ->get(route('trainees.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('training/trainees/create')
                ->has('stores')
            );

        $this->actingAs($admin)
            ->get(route('trainees.edit', $trainee))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('training/trainees/edit')
                ->where('trainee.id', $trainee->id)
            );
    }

    public function test_manager_creating_a_trainee_auto_assigns_and_sets_store(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();

        $this->actingAs($manager)
            ->post(route('trainees.store'), $this->newTrainee(['name' => 'Sam Crew']))
            ->assertSessionHasNoErrors();

        $trainee = Trainee::firstWhere('name', 'Sam Crew');
        $this->assertNotNull($trainee);
        $this->assertSame($store->id, $trainee->store_id);
        $this->assertSame($manager->id, $trainee->created_by);
        $this->assertTrue($trainee->managers()->whereKey($manager->id)->exists());
    }

    public function test_super_admin_must_choose_a_store(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->post(route('trainees.store'), $this->newTrainee(['name' => 'No Store']))
            ->assertSessionHasErrors('store_id');
    }

    public function test_multi_store_manager_picks_a_store_for_a_new_trainee(): void
    {
        [$storeA, $storeB] = Store::factory()->count(2)->create();
        $manager = User::factory()->manager($storeA)->create();
        $manager->stores()->attach($storeB->id);

        // Two stores → must choose.
        $this->actingAs($manager->fresh())
            ->post(route('trainees.store'), $this->newTrainee(['name' => 'Ambiguous']))
            ->assertSessionHasErrors('store_id');

        // Picking one of their stores works and lands the trainee there.
        $this->actingAs($manager->fresh())
            ->post(route('trainees.store'), $this->newTrainee(['name' => 'Picked', 'store_id' => $storeB->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame($storeB->id, Trainee::firstWhere('name', 'Picked')->store_id);
    }

    public function test_manager_cannot_move_a_trainee_to_a_foreign_store(): void
    {
        $store = Store::factory()->create();
        $foreign = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();
        $trainee = Trainee::factory()->forStore($store)->create();

        // The refusal must surface as an error, not a silent no-op reported as success.
        $this->actingAs($manager)
            ->put(route('trainees.update', $trainee), [
                'name' => $trainee->name,
                'store_id' => $foreign->id,
            ])
            ->assertSessionHasErrors('store_id');

        $this->assertSame($store->id, $trainee->refresh()->store_id);
    }

    public function test_manager_cannot_create_a_trainee_in_a_foreign_store(): void
    {
        $manager = User::factory()->manager()->create();
        $foreign = Store::factory()->create();

        $this->actingAs($manager)
            ->post(route('trainees.store'), $this->newTrainee(['name' => 'Sneaky', 'store_id' => $foreign->id]))
            ->assertSessionHasErrors('store_id');
    }

    public function test_manager_cannot_view_an_unassigned_trainee(): void
    {
        $manager = User::factory()->manager()->create();
        $trainee = Trainee::factory()->create();

        $this->actingAs($manager)->get(route('trainees.show', $trainee))->assertForbidden();
    }

    public function test_assigned_manager_can_view_a_trainee(): void
    {
        $this->withoutVite();
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();
        $trainee = Trainee::factory()->forStore($store)->create();
        $trainee->managers()->attach($manager);

        $this->actingAs($manager)->get(route('trainees.show', $trainee))->assertOk();
    }

    public function test_manager_cannot_update_or_delete_unassigned_trainee(): void
    {
        $manager = User::factory()->manager()->create();
        $trainee = Trainee::factory()->create();

        $this->actingAs($manager)
            ->put(route('trainees.update', $trainee), ['name' => 'Hacked'])
            ->assertForbidden();

        $this->actingAs($manager)
            ->delete(route('trainees.destroy', $trainee))
            ->assertForbidden();
    }

    public function test_only_super_admin_can_assign_managers(): void
    {
        $store = Store::factory()->create();
        $managerA = User::factory()->manager($store)->create();
        $managerB = User::factory()->manager($store)->create();
        $trainee = Trainee::factory()->forStore($store)->create();
        $trainee->managers()->attach($managerA);

        // An assigned manager may not reassign.
        $this->actingAs($managerA)
            ->put(route('trainees.managers.update', $trainee), ['manager_ids' => [$managerB->id]])
            ->assertForbidden();

        // Super admin can.
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)
            ->put(route('trainees.managers.update', $trainee), [
                'manager_ids' => [$managerA->id, $managerB->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(
            [$managerA->id, $managerB->id],
            $trainee->managers()->pluck('users.id')->all(),
        );
    }

    // --- Add Trainee form: required fields + position picker ---------------

    public function test_every_field_is_required_when_adding_a_trainee(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->post(route('trainees.store'), [])
            ->assertSessionHasErrors(['name', 'position', 'hired_at']);

        $this->assertDatabaseCount('trainees', 0);
    }

    public function test_a_position_outside_the_list_is_rejected(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->post(route('trainees.store'), $this->newTrainee(['position' => 'Cook']))
            ->assertSessionHasErrors('position');

        $this->assertDatabaseCount('trainees', 0);
    }

    public function test_crew_member_and_crew_leader_can_be_chosen(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->post(route('trainees.store'), $this->newTrainee([
                'name' => 'Lead Hand',
                'position' => Position::CrewLeader->value,
                'hired_at' => '2026-09-01',
            ]))
            ->assertSessionHasNoErrors();

        $trainee = Trainee::firstWhere('name', 'Lead Hand');
        $this->assertSame('Crew Leader', $trainee->position);
        $this->assertSame('2026-09-01', $trainee->hired_at->toDateString());
    }

    public function test_the_create_page_offers_cm_and_cl_but_hides_am_by_default(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->get(route('trainees.create'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('positionOptions', [
                    ['value' => 'Crew Member', 'label' => 'CM – Crew Member'],
                    ['value' => 'Crew Leader', 'label' => 'CL – Crew Leader'],
                ])
            );

        $this->actingAs($manager)
            ->post(route('trainees.store'), $this->newTrainee(['position' => Position::AssistantManager->value]))
            ->assertSessionHasErrors('position');
    }

    public function test_assistant_manager_becomes_available_once_enabled(): void
    {
        config(['training.assistant_manager_enabled' => true]);
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->get(route('trainees.create'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('positionOptions', 3)
                ->where('positionOptions.2', ['value' => 'Assistant Manager', 'label' => 'AM – Assistant Manager'])
            );

        $this->actingAs($manager)
            ->post(route('trainees.store'), $this->newTrainee([
                'name' => 'Assistant Pat',
                'position' => Position::AssistantManager->value,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Assistant Manager', Trainee::firstWhere('name', 'Assistant Pat')->position);
    }

    public function test_editing_a_trainee_keeps_position_and_hire_date_optional(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();
        $trainee = Trainee::factory()->forStore($store)->create();

        $this->actingAs($manager)
            ->put(route('trainees.update', $trainee), [
                'name' => 'Renamed',
                'position' => null,
                'hired_at' => null,
            ])
            ->assertSessionHasNoErrors();

        $trainee->refresh();
        $this->assertNull($trainee->position);
        $this->assertNull($trainee->hired_at);
    }

    public function test_editing_a_trainee_only_accepts_a_position_from_the_list(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();
        $trainee = Trainee::factory()->forStore($store)->create(['position' => 'Crew Member']);

        $this->actingAs($manager)
            ->put(route('trainees.update', $trainee), ['name' => $trainee->name, 'position' => 'Cook'])
            ->assertSessionHasErrors('position');

        $this->actingAs($manager)
            ->put(route('trainees.update', $trainee), ['name' => $trainee->name, 'position' => 'Crew Leader'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Crew Leader', $trainee->refresh()->position);
    }

    public function test_editing_keeps_an_older_position_that_is_not_in_the_list(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();
        $trainee = Trainee::factory()->forStore($store)->create(['position' => 'Cashier']);

        $this->actingAs($manager)
            ->get(route('trainees.edit', $trainee))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('positionOptions.0.value', 'Crew Member')
                ->where('positionOptions.1.value', 'Crew Leader')
                ->where('positionOptions.2', ['value' => 'Cashier', 'label' => 'Cashier (current, not in the list)'])
                ->missing('positionOptions.3')
            );

        $this->actingAs($manager)
            ->put(route('trainees.update', $trainee), ['name' => 'Renamed', 'position' => 'Cashier'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Cashier', $trainee->refresh()->position);
    }

    public function test_edit_page_sends_the_hire_date_as_a_plain_date(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();
        $trainee = Trainee::factory()->forStore($store)->create(['hired_at' => '2026-03-14']);

        $this->actingAs($manager)
            ->get(route('trainees.edit', $trainee))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('trainee.hired_at', '2026-03-14')
            );
    }

    public function test_the_migration_maps_known_legacy_positions_and_keeps_the_rest(): void
    {
        $store = Store::factory()->create();
        $legacy = [
            'crew member' => 'Crew Member',
            '  Shift Lead ' => 'Crew Leader',
            'SHIFT LEADER' => 'Crew Leader',
            'crew leader' => 'Crew Leader',
            'CM' => 'Crew Member',
            'assistant manager' => 'Assistant Manager',
            'Cook' => 'Cook',
            'Cashier' => 'Cashier',
        ];

        $ids = [];
        foreach (array_keys($legacy) as $position) {
            $ids[$position] = Trainee::factory()->forStore($store)->create(['position' => $position])->id;
        }
        $noPosition = Trainee::factory()->forStore($store)->create(['position' => null]);

        (require database_path('migrations/2026_09_28_174131_normalize_trainee_positions.php'))->up();

        foreach ($legacy as $original => $expected) {
            $this->assertSame($expected, Trainee::find($ids[$original])->position, "Mapping '{$original}'");
        }
        $this->assertNull($noPosition->fresh()->position);
    }

    /**
     * A complete, valid Add Trainee submission.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function newTrainee(array $overrides = []): array
    {
        return [
            'name' => 'New Trainee',
            'position' => Position::CrewMember->value,
            'hired_at' => '2026-09-15',
            ...$overrides,
        ];
    }
}
