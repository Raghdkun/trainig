<?php

namespace Tests\Feature\Training;

use App\Models\Category;
use App\Models\ChecklistItem;
use App\Models\MediaItem;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ContentMutationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    public function test_super_admin_can_update_and_delete_a_category(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->admin())
            ->put(route('training.categories.update', $category), ['title' => 'Renamed'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Renamed', $category->refresh()->title);

        $this->actingAs($this->admin())
            ->delete(route('training.categories.destroy', $category))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_super_admin_can_reorder_categories(): void
    {
        $section = Section::factory()->create();
        $a = Category::factory()->create(['section_id' => $section->id, 'order' => 0]);
        $b = Category::factory()->create(['section_id' => $section->id, 'order' => 1]);

        $this->actingAs($this->admin())
            ->post(route('training.categories.reorder', $section), [
                'items' => [
                    ['id' => $a->id, 'order' => 1],
                    ['id' => $b->id, 'order' => 0],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $a->refresh()->order);
        $this->assertSame(0, $b->refresh()->order);
    }

    public function test_super_admin_can_update_delete_and_reorder_items(): void
    {
        $category = Category::factory()->create();
        $a = ChecklistItem::factory()->create(['category_id' => $category->id, 'order' => 0]);
        $b = ChecklistItem::factory()->create(['category_id' => $category->id, 'order' => 1]);

        $this->actingAs($this->admin())
            ->put(route('training.items.update', $a), ['title' => 'Updated', 'importance' => 'moderately_important'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Updated', $a->refresh()->title);

        $this->actingAs($this->admin())
            ->post(route('training.items.reorder', $category), [
                'items' => [
                    ['id' => $a->id, 'order' => 1],
                    ['id' => $b->id, 'order' => 0],
                ],
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $a->refresh()->order);

        $this->actingAs($this->admin())
            ->delete(route('training.items.destroy', $b))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('checklist_items', ['id' => $b->id]);
    }

    public function test_super_admin_can_delete_media(): void
    {
        $media = MediaItem::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('training.media.destroy', $media))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('media_items', ['id' => $media->id]);
    }

    public function test_super_admin_can_open_the_section_editor(): void
    {
        $section = Section::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('training.sections.edit', $section))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('training/builder/section')
                ->where('section.id', $section->id)
            );
    }

    public function test_managers_cannot_mutate_content(): void
    {
        $manager = User::factory()->manager()->create();
        $category = Category::factory()->create();
        $item = ChecklistItem::factory()->create(['category_id' => $category->id]);
        $media = MediaItem::factory()->create();
        $section = Section::factory()->create();

        $this->actingAs($manager)->put(route('training.categories.update', $category), ['title' => 'X'])->assertForbidden();
        $this->actingAs($manager)->delete(route('training.categories.destroy', $category))->assertForbidden();
        $this->actingAs($manager)->put(route('training.items.update', $item), ['title' => 'X'])->assertForbidden();
        $this->actingAs($manager)->delete(route('training.items.destroy', $item))->assertForbidden();
        $this->actingAs($manager)->delete(route('training.media.destroy', $media))->assertForbidden();
        $this->actingAs($manager)->get(route('training.sections.edit', $section))->assertForbidden();
    }
}
