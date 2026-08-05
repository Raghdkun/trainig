<?php

namespace Tests\Feature\Training;

use App\Models\Category;
use App\Models\ChecklistItem;
use App\Models\Evaluation;
use App\Models\Store;
use App\Models\Trainee;
use App\Models\User;
use App\Services\Training\TraineeProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatingRequirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_items_require_a_rating_by_default(): void
    {
        $item = ChecklistItem::factory()->create();

        $this->assertTrue($item->requires_rating);
    }

    public function test_admin_can_create_an_item_that_does_not_require_a_rating(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)
            ->post(route('training.items.store', $category), [
                'title' => 'Wear a clean uniform',
                'requires_rating' => false,
            ])
            ->assertSessionHasNoErrors();

        $item = ChecklistItem::firstWhere('title', 'Wear a clean uniform');
        $this->assertNotNull($item);
        $this->assertFalse($item->requires_rating);
    }

    public function test_admin_can_toggle_requires_rating_on_an_existing_item(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $item = ChecklistItem::factory()->create(['requires_rating' => true]);

        $this->actingAs($admin)
            ->put(route('training.items.update', $item), [
                'title' => $item->title,
                'requires_rating' => false,
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($item->refresh()->requires_rating);
    }

    public function test_omitting_requires_rating_leaves_it_unchanged(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $item = ChecklistItem::factory()->withoutRating()->create();

        // A partial update that never mentions requires_rating must not flip it.
        $this->actingAs($admin)
            ->put(route('training.items.update', $item), ['title' => 'Renamed'])
            ->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame('Renamed', $item->title);
        $this->assertFalse($item->requires_rating);
    }

    public function test_a_not_rated_item_completes_without_a_score_or_note(): void
    {
        [$manager, $trainee, $item] = $this->manageableItem(requiresRating: false);

        $this->actingAs($manager)
            ->put(route('trainees.evaluations.update', [$trainee, $item]), [
                'completed' => true,
            ])
            ->assertSessionHasNoErrors();

        $evaluation = Evaluation::where('trainee_id', $trainee->id)
            ->where('checklist_item_id', $item->id)
            ->sole();

        $this->assertTrue((bool) $evaluation->completed);
        $this->assertNull($evaluation->rating);
    }

    public function test_a_submitted_score_is_discarded_for_a_not_rated_item(): void
    {
        [$manager, $trainee, $item] = $this->manageableItem(requiresRating: false);

        $this->actingAs($manager)
            ->put(route('trainees.evaluations.update', [$trainee, $item]), [
                'completed' => true,
                'rating' => 90,
                'notes' => 'a note',
            ])
            ->assertSessionHasNoErrors();

        // The note is kept, but the score is never persisted.
        $evaluation = Evaluation::where('checklist_item_id', $item->id)->sole();
        $this->assertNull($evaluation->rating);
        $this->assertSame('a note', $evaluation->notes);
    }

    public function test_a_scored_item_still_requires_a_score(): void
    {
        [$manager, $trainee, $item] = $this->manageableItem(requiresRating: true);

        $this->actingAs($manager)
            ->put(route('trainees.evaluations.update', [$trainee, $item]), [
                'completed' => true,
                'notes' => 'note but no score',
            ])
            ->assertSessionHasErrors('rating');
    }

    public function test_a_completed_not_rated_item_counts_toward_progress_but_not_the_average(): void
    {
        $store = Store::factory()->create();
        $trainee = Trainee::factory()->forStore($store)->create();
        $category = Category::factory()->create();

        $scored = ChecklistItem::factory()->create(['category_id' => $category->id]);
        $notRated = ChecklistItem::factory()->withoutRating()->create(['category_id' => $category->id]);

        Evaluation::factory()->create([
            'trainee_id' => $trainee->id,
            'checklist_item_id' => $scored->id,
            'completed' => true,
            'rating' => 80,
        ]);
        Evaluation::factory()->create([
            'trainee_id' => $trainee->id,
            'checklist_item_id' => $notRated->id,
            'completed' => true,
            'rating' => null,
        ]);

        $stats = app(TraineeProgress::class)->rosterStats([$trainee->id])[$trainee->id];

        $this->assertSame(2, $stats['total']);
        $this->assertSame(2, $stats['completed']);
        // Only the scored item feeds the average — the not-rated one is ignored.
        $this->assertSame(80.0, $stats['average_rating']);
    }

    /**
     * A manager assigned to a trainee, plus a leaf item to evaluate.
     *
     * @return array{0: User, 1: Trainee, 2: ChecklistItem}
     */
    private function manageableItem(bool $requiresRating): array
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();
        $trainee = Trainee::factory()->forStore($store)->create();
        $trainee->managers()->attach($manager);

        $item = ChecklistItem::factory()->create(['requires_rating' => $requiresRating]);

        return [$manager, $trainee, $item];
    }
}
