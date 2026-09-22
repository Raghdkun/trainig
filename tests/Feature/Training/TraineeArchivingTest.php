<?php

namespace Tests\Feature\Training;

use App\Models\ChecklistItem;
use App\Models\Evaluation;
use App\Models\Store;
use App\Models\Trainee;
use App\Models\User;
use App\Services\Training\ReportAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TraineeArchivingTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_manager_can_archive_a_trainee_but_not_restore_it(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();
        $trainee = Trainee::factory()->forStore($store)->create();
        $trainee->managers()->attach($manager);

        $this->actingAs($manager)
            ->patch(route('trainees.archive', $trainee))
            ->assertSessionHasNoErrors();

        $trainee->refresh();
        $this->assertTrue($trainee->isArchived());
        $this->assertSame($manager->id, $trainee->archived_by);

        // Once a trainee is History, only an admin may bring them back —
        // a manager is view-only from here on.
        $this->actingAs($manager)
            ->patch(route('trainees.restore', $trainee))
            ->assertForbidden();

        $this->assertTrue($trainee->refresh()->isArchived());
    }

    public function test_admin_can_restore_an_archived_trainee(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $trainee = Trainee::factory()->archived()->create();

        $this->actingAs($admin)
            ->patch(route('trainees.restore', $trainee))
            ->assertSessionHasNoErrors();

        $this->assertFalse($trainee->refresh()->isArchived());
        $this->assertNull($trainee->archived_by);
    }

    public function test_manager_cannot_edit_or_delete_an_archived_trainee(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();
        $trainee = Trainee::factory()->forStore($store)->archived()->create();
        $trainee->managers()->attach($manager);

        $this->actingAs($manager)
            ->get(route('trainees.edit', $trainee))
            ->assertForbidden();

        $this->actingAs($manager)
            ->put(route('trainees.update', $trainee), ['name' => 'Renamed'])
            ->assertForbidden();

        $this->actingAs($manager)
            ->delete(route('trainees.destroy', $trainee))
            ->assertForbidden();

        $this->assertDatabaseHas('trainees', ['id' => $trainee->id, 'name' => $trainee->name]);
    }

    public function test_manager_can_still_view_an_archived_trainee_read_only(): void
    {
        $this->withoutVite();
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();
        $trainee = Trainee::factory()->forStore($store)->archived()->create();
        $trainee->managers()->attach($manager);

        $this->actingAs($manager)
            ->get(route('trainees.show', $trainee))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('canManage', false)
                ->where('canDelete', false)
            );
    }

    public function test_admin_can_edit_and_delete_an_archived_trainee(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $trainee = Trainee::factory()->archived()->create();

        $this->actingAs($admin)
            ->put(route('trainees.update', $trainee), [
                'name' => 'Corrected Name',
                'position' => $trainee->position,
                'hired_at' => $trainee->hired_at?->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Corrected Name', $trainee->refresh()->name);

        $this->actingAs($admin)
            ->delete(route('trainees.destroy', $trainee))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('trainees', ['id' => $trainee->id]);
    }

    public function test_unassigned_manager_cannot_archive_a_trainee(): void
    {
        $manager = User::factory()->manager()->create();
        $trainee = Trainee::factory()->create();

        $this->actingAs($manager)
            ->patch(route('trainees.archive', $trainee))
            ->assertForbidden();
    }

    public function test_index_shows_active_by_default_and_archived_on_that_tab(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create();
        $active = Trainee::factory()->create(['name' => 'Active One']);
        $archived = Trainee::factory()->archived()->create(['name' => 'Archived One']);

        $this->actingAs($admin)
            ->get(route('trainees.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('trainees', 1)
                ->where('trainees.0.name', 'Active One')
                ->where('traineeCounts.active', 1)
                ->where('traineeCounts.archived', 1)
            );

        $this->actingAs($admin)
            ->get(route('trainees.index', ['tab' => 'archived']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('trainees', 1)
                ->where('trainees.0.name', 'Archived One')
            );
    }

    public function test_dashboard_trainee_counts_exclude_archived(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create();
        Trainee::factory()->create();
        Trainee::factory()->archived()->create();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('stats.trainees', 1));
    }

    public function test_evaluations_are_locked_for_archived_trainees(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();
        $trainee = Trainee::factory()->forStore($store)->archived()->create();
        $trainee->managers()->attach($manager);
        $item = ChecklistItem::factory()->create();

        $this->actingAs($manager)
            ->put(route('trainees.evaluations.update', [$trainee, $item]), [
                'completed' => true,
                'rating' => 100,
                'notes' => 'Nice try',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('evaluations', ['trainee_id' => $trainee->id]);
    }

    public function test_reports_exclude_archived_trainees_unless_included(): void
    {
        $active = Trainee::factory()->create();
        $archived = Trainee::factory()->archived()->create();
        $admin = User::factory()->superAdmin()->create();

        $analytics = app(ReportAnalytics::class);

        $default = $analytics->for($admin);
        $this->assertContains($active->id, $default->traineeIds);
        $this->assertNotContains($archived->id, $default->traineeIds);

        $withArchived = $analytics->for($admin, ['includeArchived' => true]);
        $this->assertContains($active->id, $withArchived->traineeIds);
        $this->assertContains($archived->id, $withArchived->traineeIds);
    }

    public function test_archiving_does_not_delete_evaluation_history(): void
    {
        $trainee = Trainee::factory()->create();
        $item = ChecklistItem::factory()->create();
        Evaluation::factory()->create([
            'trainee_id' => $trainee->id,
            'checklist_item_id' => $item->id,
            'completed' => true,
            'rating' => 90,
        ]);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->patch(route('trainees.archive', $trainee))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('evaluations', [
            'trainee_id' => $trainee->id,
            'checklist_item_id' => $item->id,
            'rating' => 90,
        ]);
    }
}
