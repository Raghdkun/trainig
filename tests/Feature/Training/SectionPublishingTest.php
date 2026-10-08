<?php

namespace Tests\Feature\Training;

use App\Enums\SectionStatus;
use App\Models\Category;
use App\Models\ChecklistItem;
use App\Models\Section;
use App\Models\Trainee;
use App\Models\User;
use App\Services\Training\TraineeProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectionPublishingTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_sections_are_created_as_draft(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('training.sections.store'), [
            'title' => 'Fresh Station',
        ])->assertSessionHasNoErrors();

        $section = Section::firstWhere('title', 'Fresh Station');
        $this->assertNotNull($section);
        $this->assertTrue($section->status === SectionStatus::Draft);
    }

    public function test_super_admin_can_publish_and_unpublish_a_section(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $section = Section::factory()->draft()->create();

        $this->actingAs($admin)
            ->patch(route('training.sections.publish', $section))
            ->assertSessionHasNoErrors();
        $this->assertTrue($section->refresh()->status === SectionStatus::Published);

        $this->actingAs($admin)
            ->patch(route('training.sections.unpublish', $section))
            ->assertSessionHasNoErrors();
        $this->assertTrue($section->refresh()->status === SectionStatus::Draft);
    }

    public function test_managers_cannot_publish_or_unpublish_sections(): void
    {
        $manager = User::factory()->manager()->create();
        $section = Section::factory()->draft()->create();

        $this->actingAs($manager)
            ->patch(route('training.sections.publish', $section))
            ->assertForbidden();
    }

    public function test_draft_sections_are_hidden_from_trainee_progress_and_completion_totals(): void
    {
        $published = Section::factory()->published()->create(['order' => 0]);
        $publishedCategory = Category::factory()->create(['section_id' => $published->id]);
        ChecklistItem::factory()->create(['category_id' => $publishedCategory->id]);

        $draft = Section::factory()->draft()->create(['order' => 1]);
        $draftCategory = Category::factory()->create(['section_id' => $draft->id]);
        ChecklistItem::factory()->create(['category_id' => $draftCategory->id]);

        $trainee = Trainee::factory()->create();

        $detail = app(TraineeProgress::class)->detail($trainee);

        $this->assertCount(1, $detail['sections']);
        $this->assertSame($published->id, $detail['sections'][0]['id']);

        // The draft section's item must not inflate the completion denominator.
        $this->assertSame(1, $detail['stats']['total']);
    }

    public function test_dashboard_station_count_excludes_draft_sections(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create();
        Section::factory()->published()->create();
        Section::factory()->draft()->create();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('stats.sections', 1));
    }
}
