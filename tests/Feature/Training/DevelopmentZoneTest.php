<?php

namespace Tests\Feature\Training;

use App\Enums\DevelopmentStatus;
use App\Models\Category;
use App\Models\ChecklistItem;
use App\Models\DevelopmentEvaluation;
use App\Models\DevelopmentEvaluationCriterion;
use App\Models\Evaluation;
use App\Models\Section;
use App\Models\Store;
use App\Models\Trainee;
use App\Models\User;
use App\Services\Training\TraineeProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DevelopmentZoneTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, array{criterion_id: int, rating: int}>
     */
    private function ratingsFor(Collection $criteria, int $rating = 4): array
    {
        return $criteria->map(fn (DevelopmentEvaluationCriterion $criterion): array => [
            'criterion_id' => $criterion->id,
            'rating' => $rating,
        ])->all();
    }

    public function test_assigned_manager_can_add_a_trainee_to_the_development_zone_with_an_evaluation(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();
        $trainee = Trainee::factory()->forStore($store)->create();
        $trainee->managers()->attach($manager);

        $criteria = DevelopmentEvaluationCriterion::active()->get();

        $this->actingAs($manager)
            ->post(route('trainees.development.store', $trainee), [
                'notes' => 'Needs help with speed.',
                'ratings' => $this->ratingsFor($criteria, 3),
            ])
            ->assertSessionHasNoErrors();

        $trainee->refresh();
        $this->assertSame(DevelopmentStatus::Pending, $trainee->development_status);

        $evaluation = $trainee->latestDevelopmentEvaluation;
        $this->assertNotNull($evaluation);
        $this->assertSame($manager->id, $evaluation->evaluated_by);
        $this->assertSame('Needs help with speed.', $evaluation->notes);
        $this->assertCount($criteria->count(), $evaluation->ratings);
        $this->assertTrue($evaluation->ratings->every(fn ($rating) => $rating->rating === 3));
    }

    public function test_evaluation_requires_a_rating_for_every_active_criterion(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();
        $trainee = Trainee::factory()->forStore($store)->create();
        $trainee->managers()->attach($manager);

        $criteria = DevelopmentEvaluationCriterion::active()->get();
        $ratings = $this->ratingsFor($criteria->skip(1));

        $this->actingAs($manager)
            ->post(route('trainees.development.store', $trainee), [
                'ratings' => $ratings,
            ])
            ->assertSessionHasErrors('ratings');

        $this->assertNull($trainee->refresh()->development_status);
    }

    public function test_unassigned_manager_cannot_add_a_trainee_to_the_development_zone(): void
    {
        $manager = User::factory()->manager()->create();
        $trainee = Trainee::factory()->create();

        $this->actingAs($manager)
            ->post(route('trainees.development.store', $trainee), [
                'ratings' => $this->ratingsFor(DevelopmentEvaluationCriterion::active()->get()),
            ])
            ->assertForbidden();
    }

    public function test_admin_can_finalize_a_plan_and_move_pending_to_active(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $trainee = Trainee::factory()->developmentStatus(DevelopmentStatus::Pending)->create();
        $item = ChecklistItem::factory()->create();

        $this->actingAs($admin)
            ->put(route('trainees.development.update', $trainee), [
                'item_ids' => [$item->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(DevelopmentStatus::Active, $trainee->refresh()->development_status);
    }

    public function test_manager_cannot_edit_the_development_plan(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();
        $trainee = Trainee::factory()->forStore($store)->developmentStatus(DevelopmentStatus::Pending)->create();
        $trainee->managers()->attach($manager);

        $item = ChecklistItem::factory()->create();

        $this->actingAs($manager)
            ->put(route('trainees.development.update', $trainee), [
                'item_ids' => [$item->id],
            ])
            ->assertForbidden();
    }

    public function test_admin_can_mark_an_active_trainee_completed(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $trainee = Trainee::factory()->developmentStatus(DevelopmentStatus::Active)->create();

        $this->actingAs($admin)
            ->patch(route('trainees.development.complete', $trainee))
            ->assertSessionHasNoErrors();

        $this->assertSame(DevelopmentStatus::Completed, $trainee->refresh()->development_status);
    }

    public function test_completing_a_non_active_trainee_is_rejected(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $trainee = Trainee::factory()->developmentStatus(DevelopmentStatus::Pending)->create();

        $this->actingAs($admin)
            ->patch(route('trainees.development.complete', $trainee))
            ->assertSessionHasErrors('trainee');

        $this->assertSame(DevelopmentStatus::Pending, $trainee->refresh()->development_status);
    }

    public function test_manager_cannot_mark_a_trainee_completed(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();
        $trainee = Trainee::factory()->forStore($store)->developmentStatus(DevelopmentStatus::Active)->create();
        $trainee->managers()->attach($manager);

        $this->actingAs($manager)
            ->patch(route('trainees.development.complete', $trainee))
            ->assertForbidden();
    }

    public function test_only_admin_can_remove_a_trainee_from_the_development_zone(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();
        $trainee = Trainee::factory()->forStore($store)->developmentStatus(DevelopmentStatus::Active)->create();
        $trainee->managers()->attach($manager);

        $this->actingAs($manager)
            ->delete(route('trainees.development.destroy', $trainee))
            ->assertForbidden();

        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->delete(route('trainees.development.destroy', $trainee))
            ->assertSessionHasNoErrors();

        $this->assertNull($trainee->refresh()->development_status);
    }

    public function test_manager_can_view_the_development_zone_detail_read_only(): void
    {
        $this->withoutVite();
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();
        $trainee = Trainee::factory()->forStore($store)->developmentStatus(DevelopmentStatus::Active)->create();
        $trainee->managers()->attach($manager);

        $item = ChecklistItem::factory()->create();
        $trainee->developmentItems()->attach($item->id);

        $this->actingAs($manager)
            ->get(route('development-zone.show', $trainee))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('training/development-zone/show')
                ->where('canManagePlan', false)
                ->has('developmentPlan.sections', 1)
                ->has('developmentPlan.sections.0.categories.0.items', 1)
            );
    }

    public function test_admin_can_manage_evaluation_criteria(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->post(route('admin.development-criteria.store'), [
                'label' => 'Communication',
                'description' => null,
            ])
            ->assertSessionHasNoErrors();

        $criterion = DevelopmentEvaluationCriterion::where('label', 'Communication')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.development-criteria.update', $criterion), [
                'label' => 'Communication',
                'description' => null,
                'is_active' => false,
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($criterion->refresh()->is_active);
    }

    public function test_admin_can_delete_an_unused_evaluation_criterion(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $criterion = DevelopmentEvaluationCriterion::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.development-criteria.destroy', $criterion))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('development_evaluation_criteria', ['id' => $criterion->id]);
    }

    public function test_a_criterion_already_used_in_an_evaluation_cannot_be_deleted(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $criterion = DevelopmentEvaluationCriterion::factory()->create();
        $evaluation = DevelopmentEvaluation::factory()->create();
        $evaluation->ratings()->create([
            'development_evaluation_criterion_id' => $criterion->id,
            'rating' => 4,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.development-criteria.destroy', $criterion))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('development_evaluation_criteria', ['id' => $criterion->id]);
    }

    public function test_manager_cannot_delete_an_evaluation_criterion(): void
    {
        $manager = User::factory()->manager()->create();
        $criterion = DevelopmentEvaluationCriterion::factory()->create();

        $this->actingAs($manager)
            ->delete(route('admin.development-criteria.destroy', $criterion))
            ->assertForbidden();
    }

    public function test_manager_cannot_manage_evaluation_criteria(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->post(route('admin.development-criteria.store'), [
                'label' => 'Communication',
            ])
            ->assertForbidden();
    }

    public function test_updating_the_plan_only_accepts_leaf_items_from_published_sections(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $trainee = Trainee::factory()->developmentStatus(DevelopmentStatus::Pending)->create();

        $publishedSection = Section::factory()->published()->create();
        $publishedCategory = Category::factory()->create(['section_id' => $publishedSection->id]);
        $validLeaf = ChecklistItem::factory()->create(['category_id' => $publishedCategory->id]);

        $draftSection = Section::factory()->draft()->create();
        $draftCategory = Category::factory()->create(['section_id' => $draftSection->id]);
        $itemInDraftSection = ChecklistItem::factory()->create(['category_id' => $draftCategory->id]);

        $parentWithChildren = ChecklistItem::factory()->create(['category_id' => $publishedCategory->id]);
        ChecklistItem::factory()->child($parentWithChildren)->create();

        $this->actingAs($admin)
            ->put(route('trainees.development.update', $trainee), [
                'item_ids' => [$validLeaf->id, $itemInDraftSection->id, $parentWithChildren->id, 999999],
            ])
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(
            [$validLeaf->id],
            $trainee->developmentItems()->pluck('checklist_items.id')->all(),
        );
    }

    public function test_development_plan_reuses_the_standard_evaluation_and_reports_its_own_completion(): void
    {
        $trainee = Trainee::factory()->developmentStatus(DevelopmentStatus::Active)->create();
        $itemA = ChecklistItem::factory()->create();
        $itemB = ChecklistItem::factory()->create();
        $trainee->developmentItems()->attach([$itemA->id, $itemB->id]);

        Evaluation::factory()->create([
            'trainee_id' => $trainee->id,
            'checklist_item_id' => $itemA->id,
            'completed' => true,
            'rating' => 80,
        ]);

        $plan = app(TraineeProgress::class)->developmentPlan($trainee);

        $items = collect($plan['sections'])
            ->flatMap(fn (array $section) => $section['categories'])
            ->flatMap(fn (array $category) => $category['items']);

        $this->assertSame(['completed' => 1, 'total' => 2], $plan['stats']);
        $this->assertCount(2, $items);
        $scoredItem = $items->firstWhere('id', $itemA->id);
        $this->assertTrue($scoredItem['evaluation']['completed']);
        $this->assertSame(80, $scoredItem['evaluation']['rating']);
    }

    public function test_dashboard_development_zone_lists_flagged_trainees_with_progress(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create();

        $flagged = Trainee::factory()->developmentStatus(DevelopmentStatus::Active)->create(['name' => 'Needs Help']);
        $item = ChecklistItem::factory()->create();
        $flagged->developmentItems()->attach($item->id);
        Evaluation::factory()->create([
            'trainee_id' => $flagged->id,
            'checklist_item_id' => $item->id,
            'completed' => true,
        ]);

        Trainee::factory()->create(['name' => 'Not Flagged']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('developmentZone', 1)
                ->where('developmentZone.0.name', 'Needs Help')
                ->where('developmentZone.0.status', 'active')
                ->where('developmentZone.0.stats.completed', 1)
                ->where('developmentZone.0.stats.total', 1)
            );
    }

    public function test_manager_only_sees_their_own_flagged_trainees_in_the_development_zone(): void
    {
        $this->withoutVite();
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();

        $mine = Trainee::factory()->forStore($store)->developmentStatus(DevelopmentStatus::Pending)->create();
        Trainee::factory()->developmentStatus(DevelopmentStatus::Pending)->create();

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('developmentZone', 1)
                ->where('developmentZone.0.id', $mine->id)
            );
    }

    public function test_guests_are_redirected_from_the_development_zone_page(): void
    {
        $this->get(route('development-zone.index'))->assertRedirect(route('login'));
    }

    public function test_both_managers_and_admins_can_open_the_development_zone_page(): void
    {
        $this->withoutVite();
        $manager = User::factory()->manager()->create();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($manager)->get(route('development-zone.index'))->assertOk();
        $this->actingAs($admin)->get(route('development-zone.index'))->assertOk();
    }

    public function test_the_development_zone_page_lists_flagged_trainees_and_respects_store_filter(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create();

        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();
        $flaggedA = Trainee::factory()->forStore($storeA)->developmentStatus(DevelopmentStatus::Pending)->create();
        $flaggedB = Trainee::factory()->forStore($storeB)->developmentStatus(DevelopmentStatus::Pending)->create();
        Trainee::factory()->forStore($storeA)->create();

        $this->actingAs($admin)
            ->get(route('development-zone.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('training/development-zone/index')
                ->has('trainees', 2)
            );

        $this->actingAs($admin)
            ->get(route('development-zone.index', ['store' => $storeA->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('trainees', 1)
                ->where('trainees.0.id', $flaggedA->id)
            );

        $this->assertNotNull($flaggedB);
    }
}
