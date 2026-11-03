<?php

namespace Tests\Feature\Training;

use App\Actions\Training\RecordEvaluation;
use App\Models\Category;
use App\Models\ChecklistItem;
use App\Models\Evaluation;
use App\Models\Section;
use App\Models\Trainee;
use App\Models\User;
use App\Services\Training\ReportAnalytics;
use App\Services\Training\TraineeProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stations and items can be marked optional (counts_toward_progress off): they
 * can still be worked through, but never count toward completion, the average
 * score, the current step or any report.
 */
class OptionalStationTest extends TestCase
{
    use RefreshDatabase;

    private function progress(): TraineeProgress
    {
        return app(TraineeProgress::class);
    }

    private function analytics(): ReportAnalytics
    {
        return app(ReportAnalytics::class);
    }

    private function category(bool $counted = true): Category
    {
        $section = $counted
            ? Section::factory()->create()
            : Section::factory()->uncounted()->create();

        return Category::factory()->create(['section_id' => $section->id]);
    }

    private function evaluate(Trainee $trainee, ChecklistItem $item, int $rating, ?User $by = null): void
    {
        Evaluation::factory()->completed()->create([
            'trainee_id' => $trainee->id,
            'checklist_item_id' => $item->id,
            'rating' => $rating,
            'evaluated_by' => $by?->id,
        ]);
    }

    private function record(Trainee $trainee, ChecklistItem $item, User $by): void
    {
        app(RecordEvaluation::class)->handle(
            $trainee,
            $item,
            ['completed' => true, 'rating' => null, 'notes' => null],
            $by,
        );
    }

    private function isCompleted(Trainee $trainee, ChecklistItem $item): bool
    {
        return (bool) Evaluation::where('trainee_id', $trainee->id)
            ->where('checklist_item_id', $item->id)
            ->value('completed');
    }

    public function test_an_optional_station_is_left_out_of_completion_score_and_reports(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $trainee = Trainee::factory()->create();
        $counted = $this->category();
        $optional = $this->category(counted: false);

        $required = ChecklistItem::factory()->create(['category_id' => $counted->id]);
        $game = ChecklistItem::factory()->create(['category_id' => $optional->id]);
        $this->evaluate($trainee, $required, 80);
        $this->evaluate($trainee, $game, 20);

        $this->assertSame(
            ['completed' => 1, 'total' => 1, 'average_rating' => 80.0],
            $this->progress()->rosterStats([$trainee->id])[$trainee->id],
        );

        $scope = $this->analytics()->for($admin);
        $overview = $this->analytics()->overview($scope);

        $this->assertSame(100, $overview['completion']);
        $this->assertSame(80.0, $overview['average_score']);
        $this->assertSame(1, $overview['fully_trained']);
        $this->assertSame(1, $overview['evaluations_recorded']);
        $this->assertSame(1, array_sum(array_column($this->analytics()->scoreDistribution($scope), 'count')));

        $titles = collect($this->analytics()->stationInsights($scope)['sections'])->pluck('title');
        $this->assertTrue($titles->contains($counted->section->title));
        $this->assertFalse($titles->contains($optional->section->title));
    }

    public function test_an_optional_item_inside_a_counted_station_is_left_out(): void
    {
        $trainee = Trainee::factory()->create();
        $category = $this->category();
        $required = ChecklistItem::factory()->create(['category_id' => $category->id, 'order' => 0]);
        $optional = ChecklistItem::factory()->uncounted()->create(['category_id' => $category->id, 'order' => 1]);
        $this->evaluate($trainee, $required, 90);
        $this->evaluate($trainee, $optional, 10);

        $detail = $this->progress()->detail($trainee);

        $this->assertSame(
            ['completed' => 1, 'total' => 1, 'average_rating' => 90.0],
            $detail['stats'],
        );
        $this->assertSame(90.0, $detail['sections'][0]['average_rating']);
        $this->assertTrue($detail['sections'][0]['counts_toward_progress']);
        $this->assertTrue($detail['sections'][0]['categories'][0]['items'][0]['counts_toward_progress']);
        $this->assertFalse($detail['sections'][0]['categories'][0]['items'][1]['counts_toward_progress']);
    }

    public function test_an_optional_station_shows_no_average_but_keeps_its_progress_data(): void
    {
        $trainee = Trainee::factory()->create();
        $optional = $this->category(counted: false);
        $game = ChecklistItem::factory()->create(['category_id' => $optional->id]);
        $this->evaluate($trainee, $game, 70);

        $section = $this->progress()->detail($trainee)['sections'][0];

        $this->assertFalse($section['counts_toward_progress']);
        $this->assertNull($section['average_rating']);
        $this->assertTrue($section['categories'][0]['items'][0]['evaluation']['completed']);
    }

    public function test_an_optional_item_is_never_the_current_step(): void
    {
        $trainee = Trainee::factory()->create();
        $category = $this->category();
        ChecklistItem::factory()->uncounted()->create(['category_id' => $category->id, 'order' => 0]);
        $required = ChecklistItem::factory()->create(['category_id' => $category->id, 'order' => 1]);

        $this->assertSame($required->id, $this->progress()->detail($trainee)['currentStepId']);
    }

    public function test_manager_activity_and_importance_breakdown_ignore_optional_items(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $manager = User::factory()->manager()->create();
        $trainee = Trainee::factory()->create();
        $required = ChecklistItem::factory()->create(['category_id' => $this->category()->id]);
        $game = ChecklistItem::factory()->create(['category_id' => $this->category(counted: false)->id]);
        $this->evaluate($trainee, $required, 80, $manager);
        $this->evaluate($trainee, $game, 20, $manager);

        $scope = $this->analytics()->for($admin);

        $row = collect($this->analytics()->managerActivity($scope))->firstWhere('id', $manager->id);
        $this->assertSame(1, $row['evaluations_recorded']);
        $this->assertSame(80.0, $row['average_score']);

        $this->assertSame(1, array_sum(array_column($this->analytics()->importanceBreakdown($scope), 'evaluations')));
    }

    public function test_an_optional_sub_step_does_not_hold_back_its_parent(): void
    {
        $manager = User::factory()->manager()->create();
        $trainee = Trainee::factory()->create();
        $parent = ChecklistItem::factory()->create(['category_id' => $this->category()->id]);
        $required = ChecklistItem::factory()->child($parent)->create();
        ChecklistItem::factory()->child($parent)->uncounted()->create();

        $this->record($trainee, $required, $manager);

        $this->assertTrue($this->isCompleted($trainee, $parent));
    }

    public function test_a_parent_whose_sub_steps_are_all_optional_still_needs_them_all(): void
    {
        $manager = User::factory()->manager()->create();
        $trainee = Trainee::factory()->create();
        $parent = ChecklistItem::factory()->create(['category_id' => $this->category()->id]);
        $first = ChecklistItem::factory()->child($parent)->uncounted()->create();
        $second = ChecklistItem::factory()->child($parent)->uncounted()->create();

        $this->record($trainee, $first, $manager);
        $this->assertFalse($this->isCompleted($trainee, $parent));

        $this->record($trainee, $second, $manager);
        $this->assertTrue($this->isCompleted($trainee, $parent));
    }

    public function test_a_station_defaults_to_counted_and_can_be_marked_optional(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->post(route('training.sections.store'), ['title' => 'Games'])
            ->assertSessionHasNoErrors();

        $games = Section::where('title', 'Games')->firstOrFail();
        $this->assertTrue($games->counts_toward_progress);

        $this->actingAs($admin)
            ->put(route('training.sections.update', $games), ['title' => 'Games', 'counts_toward_progress' => false])
            ->assertSessionHasNoErrors();

        $this->assertFalse($games->refresh()->counts_toward_progress);
    }

    public function test_changing_an_items_flag_cascades_to_its_sub_items(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $parent = ChecklistItem::factory()->create(['category_id' => $this->category()->id]);
        $child = ChecklistItem::factory()->child($parent)->create();

        $this->actingAs($admin)
            ->put(route('training.items.update', $parent), ['title' => $parent->title, 'counts_toward_progress' => false])
            ->assertSessionHasNoErrors();

        $this->assertFalse($parent->refresh()->counts_toward_progress);
        $this->assertFalse($child->refresh()->counts_toward_progress);

        $this->actingAs($admin)
            ->put(route('training.items.update', $parent), ['title' => $parent->title, 'counts_toward_progress' => true])
            ->assertSessionHasNoErrors();

        $this->assertTrue($parent->refresh()->counts_toward_progress);
        $this->assertTrue($child->refresh()->counts_toward_progress);
    }

    public function test_a_sub_item_of_an_optional_item_is_always_optional(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $parent = ChecklistItem::factory()->uncounted()->create(['category_id' => $this->category()->id]);
        $child = ChecklistItem::factory()->child($parent)->uncounted()->create();

        $this->actingAs($admin)
            ->put(route('training.items.update', $child), ['title' => $child->title, 'counts_toward_progress' => true])
            ->assertSessionHasNoErrors();

        $this->assertFalse($child->refresh()->counts_toward_progress);

        $this->actingAs($admin)
            ->post(route('training.items.store', $parent->category_id), [
                'title' => 'Brand new step',
                'parent_id' => $parent->id,
                'counts_toward_progress' => true,
            ])
            ->assertSessionHasNoErrors();

        $created = ChecklistItem::where('title', 'Brand new step')->firstOrFail();
        $this->assertFalse($created->counts_toward_progress);
    }

    public function test_items_count_by_default(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $category = $this->category();

        $this->actingAs($admin)
            ->post(route('training.items.store', $category), ['title' => 'Wash hands'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(ChecklistItem::where('title', 'Wash hands')->firstOrFail()->counts_toward_progress);
    }
}
