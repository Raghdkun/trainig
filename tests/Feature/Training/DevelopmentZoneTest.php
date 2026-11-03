<?php

namespace Tests\Feature\Training;

use App\Enums\DevelopmentStatus;
use App\Enums\EvaluationGrade;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentSkill;
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
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DevelopmentZoneTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A station/skill with a yes/no and a level question.
     *
     * @return array{0: AssessmentSkill, 1: AssessmentQuestion, 2: AssessmentQuestion}
     */
    private function stationWithQuestions(string $name = 'Making'): array
    {
        $skill = AssessmentSkill::factory()->create(['name' => $name]);
        $yesNo = AssessmentQuestion::factory()->create(['assessment_skill_id' => $skill->id]);
        $level = AssessmentQuestion::factory()->level()->create(['assessment_skill_id' => $skill->id]);

        return [$skill, $yesNo, $level];
    }

    public function test_assigned_manager_can_add_a_trainee_to_the_development_zone_with_an_evaluation(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();
        $trainee = Trainee::factory()->forStore($store)->create();
        $trainee->managers()->attach($manager);

        [$station, $yesNo, $level] = $this->stationWithQuestions();

        $this->actingAs($manager)
            ->post(route('trainees.development.store', $trainee), [
                'notes' => 'Needs help with speed.',
                'grade' => 'C',
                'points' => 62,
                'answers' => [$yesNo->id => 1, $level->id => 2],
            ])
            ->assertSessionHasNoErrors();

        $trainee->refresh();
        $this->assertSame(DevelopmentStatus::Pending, $trainee->development_status);

        $evaluation = $trainee->latestDevelopmentEvaluation;
        $this->assertNotNull($evaluation);
        $this->assertSame($manager->id, $evaluation->evaluated_by);
        $this->assertSame('Needs help with speed.', $evaluation->notes);
        $this->assertSame(EvaluationGrade::C, $evaluation->grade);
        $this->assertSame(62, $evaluation->points);
        $this->assertFalse($evaluation->is_reassessment);
        $this->assertCount(2, $evaluation->answers);
        // Yes (100%) + level 2 of 5 (40%) → 70% → 3.5 stars.
        $this->assertSame(3.5, $evaluation->skillScores()->sole()->stars);
        $this->assertSame($station->id, $evaluation->skillScores()->sole()->assessment_skill_id);
        $this->assertSame('Making', $evaluation->skillScores()->sole()->skill_name);
    }

    public function test_evaluation_requires_an_answer_to_every_assessment_question(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();
        $trainee = Trainee::factory()->forStore($store)->create();
        $trainee->managers()->attach($manager);
        [, $yesNo, $level] = $this->stationWithQuestions();

        $this->actingAs($manager)
            ->post(route('trainees.development.store', $trainee), [
                'grade' => 'B',
                'points' => 80,
                'answers' => [$yesNo->id => 1],
            ])
            ->assertSessionHasErrors("answers.{$level->id}");

        $this->actingAs($manager)
            ->post(route('trainees.development.store', $trainee), [
                'grade' => 'B',
                'points' => 80,
                'answers' => [$yesNo->id => 2, $level->id => 6],
            ])
            ->assertSessionHasErrors(["answers.{$yesNo->id}", "answers.{$level->id}"]);

        $this->assertNull($trainee->refresh()->development_status);
    }

    public function test_unassigned_manager_cannot_add_a_trainee_to_the_development_zone(): void
    {
        $manager = User::factory()->manager()->create();
        $trainee = Trainee::factory()->create();

        $this->actingAs($manager)
            ->post(route('trainees.development.store', $trainee), [
                'grade' => 'B',
                'points' => 80,
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

    public function test_saving_an_empty_plan_keeps_the_trainee_pending(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $trainee = Trainee::factory()->developmentStatus(DevelopmentStatus::Pending)->create();

        $this->actingAs($admin)
            ->put(route('trainees.development.update', $trainee), ['item_ids' => []])
            ->assertSessionHasNoErrors();

        $this->assertSame(DevelopmentStatus::Pending, $trainee->refresh()->development_status);
    }

    public function test_a_new_employees_trainee_profile_redirects_to_their_development_zone_page(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $employee = Trainee::factory()->developmentOnly()->developmentStatus(DevelopmentStatus::Active)->create();

        $this->actingAs($admin)
            ->get(route('trainees.show', $employee))
            ->assertRedirect(route('development-zone.show', $employee));
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

    public function test_admin_can_reopen_a_completed_trainee_back_to_active(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $trainee = Trainee::factory()->developmentStatus(DevelopmentStatus::Completed)->create();

        $this->actingAs($admin)
            ->patch(route('trainees.development.reopen', $trainee))
            ->assertSessionHasNoErrors();

        $this->assertSame(DevelopmentStatus::Active, $trainee->refresh()->development_status);
    }

    public function test_reopening_a_trainee_that_is_not_completed_is_rejected(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $pending = Trainee::factory()->developmentStatus(DevelopmentStatus::Pending)->create();
        $active = Trainee::factory()->developmentStatus(DevelopmentStatus::Active)->create();

        $this->actingAs($admin)
            ->patch(route('trainees.development.reopen', $pending))
            ->assertSessionHasErrors('trainee');

        $this->actingAs($admin)
            ->patch(route('trainees.development.reopen', $active))
            ->assertSessionHasErrors('trainee');

        $this->assertSame(DevelopmentStatus::Pending, $pending->refresh()->development_status);
        $this->assertSame(DevelopmentStatus::Active, $active->refresh()->development_status);
    }

    public function test_manager_cannot_reopen_a_completed_trainee(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();
        $trainee = Trainee::factory()->forStore($store)->developmentStatus(DevelopmentStatus::Completed)->create();
        $trainee->managers()->attach($manager);

        $this->actingAs($manager)
            ->patch(route('trainees.development.reopen', $trainee))
            ->assertForbidden();

        $this->assertSame(DevelopmentStatus::Completed, $trainee->refresh()->development_status);
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

    public function test_evaluation_requires_a_grade_and_points_between_0_and_100(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();
        $trainee = Trainee::factory()->forStore($store)->create();

        $this->actingAs($manager)
            ->post(route('trainees.development.store', $trainee), [])
            ->assertSessionHasErrors(['grade', 'points']);

        $this->actingAs($manager)
            ->post(route('trainees.development.store', $trainee), ['grade' => 'E', 'points' => 101])
            ->assertSessionHasErrors(['grade', 'points']);

        $this->assertNull($trainee->refresh()->development_status);
    }

    /**
     * @return array<string, mixed>
     */
    private function newEmployeePayload(array $overrides = []): array
    {
        return [
            'name' => 'Jordan Rivera',
            'hired_at' => '2026-03-15',
            'position' => 'Crew Leader',
            'grade' => 'D',
            'points' => 45,
            'notes' => 'Struggles on the Making station.',
            ...$overrides,
        ];
    }

    public function test_manager_can_add_a_new_employee_straight_into_the_development_zone(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();

        $this->actingAs($manager)
            ->post(route('development-zone.employees.store'), $this->newEmployeePayload())
            ->assertSessionHasNoErrors();

        $employee = Trainee::where('name', 'Jordan Rivera')->firstOrFail();
        $this->assertTrue($employee->development_only);
        $this->assertSame($store->id, $employee->store_id);
        $this->assertSame('Crew Leader', $employee->position);
        $this->assertSame('2026-03-15', $employee->hired_at->toDateString());
        $this->assertSame($manager->id, $employee->created_by);
        $this->assertTrue($employee->managers->contains($manager));
        $this->assertSame(DevelopmentStatus::Pending, $employee->development_status);

        $evaluation = $employee->latestDevelopmentEvaluation;
        $this->assertSame(EvaluationGrade::D, $evaluation->grade);
        $this->assertSame(45, $evaluation->points);
        $this->assertSame($manager->id, $evaluation->evaluated_by);
    }

    public function test_new_employee_validation(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();

        $this->actingAs($manager)
            ->post(route('development-zone.employees.store'), $this->newEmployeePayload([
                'name' => '',
                'hired_at' => '',
                'position' => 'Astronaut',
                'grade' => '',
                'points' => 150,
            ]))
            ->assertSessionHasErrors(['name', 'hired_at', 'position', 'grade', 'points']);

        $this->actingAs($manager)
            ->post(route('development-zone.employees.store'), [])
            ->assertSessionHasErrors(['name', 'hired_at', 'position', 'grade', 'points']);

        $this->assertDatabaseCount('trainees', 0);
    }

    public function test_new_employee_hire_date_cannot_be_in_the_future(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();

        $this->actingAs($manager)
            ->post(route('development-zone.employees.store'), $this->newEmployeePayload([
                'hired_at' => now()->addDay()->toDateString(),
            ]))
            ->assertSessionHasErrors('hired_at');

        $this->assertDatabaseCount('trainees', 0);

        $this->actingAs($manager)
            ->post(route('development-zone.employees.store'), $this->newEmployeePayload([
                'hired_at' => now()->toDateString(),
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_multi_store_manager_must_pick_one_of_their_own_stores(): void
    {
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();
        $foreign = Store::factory()->create();
        $manager = User::factory()->manager($storeA)->create();
        $manager->stores()->attach($storeB);

        $this->actingAs($manager)
            ->post(route('development-zone.employees.store'), $this->newEmployeePayload())
            ->assertSessionHasErrors('store_id');

        $this->actingAs($manager)
            ->post(route('development-zone.employees.store'), $this->newEmployeePayload(['store_id' => $foreign->id]))
            ->assertSessionHasErrors('store_id');

        $this->assertDatabaseCount('trainees', 0);

        $this->actingAs($manager)
            ->post(route('development-zone.employees.store'), $this->newEmployeePayload(['store_id' => $storeB->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($storeB->id, Trainee::sole()->store_id);
    }

    public function test_new_employees_stay_off_the_trainee_roster_but_appear_in_the_development_zone(): void
    {
        $this->withoutVite();
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();
        $admin = User::factory()->superAdmin()->create();
        $trainee = Trainee::factory()->forStore($store)->create(['name' => 'Regular Trainee']);
        $employee = Trainee::factory()->forStore($store)->developmentOnly()
            ->developmentStatus(DevelopmentStatus::Pending)->create(['name' => 'Zone Only']);

        $this->actingAs($manager)
            ->get(route('trainees.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('trainees', 1)
                ->where('trainees.0.id', $trainee->id)
                ->where('traineeCounts.active', 1)
            );

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('managerStats.trainees', 1)
                ->has('developmentZone', 1)
                ->where('developmentZone.0.id', $employee->id)
            );

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('stats.trainees', 1));

        $this->actingAs($manager)
            ->get(route('development-zone.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('trainees', 1)
                ->where('trainees.0.id', $employee->id)
                ->where('trainees.0.development_only', true)
                ->where('canAddToZone', true)
            );

        $this->actingAs($manager)
            ->get(route('development-zone.create'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('training/development-zone/create')
                ->has('addableTrainees', 1)
                ->where('addableTrainees.0.id', $trainee->id)
                ->where('canAddEmployee', true)
            );
    }

    public function test_development_zone_detail_shows_grade_and_points(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create();
        $employee = Trainee::factory()->developmentOnly()->developmentStatus(DevelopmentStatus::Pending)->create();
        DevelopmentEvaluation::factory()->create([
            'trainee_id' => $employee->id,
            'grade' => 'A',
            'points' => 91,
        ]);

        $this->actingAs($admin)
            ->get(route('development-zone.show', $employee))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('trainee.development_only', true)
                ->where('evaluation.grade', 'A')
                ->where('evaluation.points', 91)
            );
    }

    public function test_create_page_lists_skill_questions_and_preselects_a_requested_trainee(): void
    {
        $this->withoutVite();
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();
        $trainee = Trainee::factory()->forStore($store)->create();
        $foreignTrainee = Trainee::factory()->create();
        $skill = AssessmentSkill::factory()->create([
            'name' => 'Dough and Sauce Preparation',
            'description' => str_repeat('A very long description. ', 20),
        ]);
        $question = AssessmentQuestion::factory()->percentage()->create([
            'assessment_skill_id' => $skill->id,
            'prompt' => str_repeat('A very long question. ', 20),
        ]);
        AssessmentQuestion::factory()->inactive()->create(['assessment_skill_id' => $skill->id]);
        // An inactive skill, and a skill without questions, aren't asked.
        AssessmentQuestion::factory()->create(['assessment_skill_id' => AssessmentSkill::factory()->inactive()]);
        AssessmentSkill::factory()->create();

        $this->actingAs($manager)
            ->get(route('development-zone.create', ['trainee' => $trainee->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('training/development-zone/create')
                ->where('selectedTraineeId', $trainee->id)
                ->has('skills', 1)
                ->where('skills.0.name', 'Dough and Sauce Preparation')
                ->where('skills.0.description', $skill->description)
                ->has('skills.0.questions', 1)
                ->where('skills.0.questions.0.id', $question->id)
                ->where('skills.0.questions.0.prompt', $question->prompt)
                ->where('skills.0.questions.0.answer_type', 'percentage')
                ->has('gradeOptions', 4)
            );

        // A trainee the manager can't see is never preselected.
        $this->actingAs($manager)
            ->get(route('development-zone.create', ['trainee' => $foreignTrainee->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('selectedTraineeId', null));
    }

    public function test_submitting_an_evaluation_redirects_to_the_development_zone_detail(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();
        $trainee = Trainee::factory()->forStore($store)->create();

        $this->actingAs($manager)
            ->post(route('trainees.development.store', $trainee), [
                'grade' => 'B',
                'points' => 77,
            ])
            ->assertRedirect(route('development-zone.show', $trainee));

        $this->actingAs($manager)
            ->post(route('development-zone.employees.store'), $this->newEmployeePayload())
            ->assertRedirect(route('development-zone.show', Trainee::where('name', 'Jordan Rivera')->sole()));
    }
}
