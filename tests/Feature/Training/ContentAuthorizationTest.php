<?php

namespace Tests\Feature\Training;

use App\Models\Category;
use App\Models\ChecklistItem;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ContentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_the_builder(): void
    {
        $this->get(route('training.sections.index'))->assertRedirect(route('login'));
    }

    public function test_managers_cannot_access_the_builder(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->get(route('training.sections.index'))->assertForbidden();
    }

    public function test_super_admin_can_view_the_builder(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create();
        Section::factory()->create();

        $this->actingAs($admin)
            ->get(route('training.sections.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('training/builder/index')
                ->has('sections', 1)
            );
    }

    public function test_managers_cannot_create_content(): void
    {
        $manager = User::factory()->manager()->create();
        $section = Section::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($manager)->post(route('training.sections.store'), ['title' => 'X'])->assertForbidden();
        $this->actingAs($manager)->post(route('training.categories.store', $section), ['title' => 'X'])->assertForbidden();
        $this->actingAs($manager)->post(route('training.items.store', $category), [
            'title' => 'X', 'importance' => 'highly_important',
        ])->assertForbidden();
    }

    public function test_super_admin_can_crud_sections(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('training.sections.store'), [
            'title' => 'Dough & Sauce',
        ])->assertSessionHasNoErrors();

        $section = Section::firstWhere('title', 'Dough & Sauce');
        $this->assertNotNull($section);

        $this->actingAs($admin)->put(route('training.sections.update', $section), [
            'title' => 'Dough Station',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Dough Station', $section->refresh()->title);

        $this->actingAs($admin)->delete(route('training.sections.destroy', $section))
            ->assertRedirect(route('training.sections.index'));
        $this->assertDatabaseMissing('sections', ['id' => $section->id]);
    }

    public function test_super_admin_can_build_the_hierarchy(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $section = Section::factory()->create();

        $this->actingAs($admin)->post(route('training.categories.store', $section), [
            'title' => 'Topping Prep',
        ])->assertSessionHasNoErrors();
        $category = Category::firstWhere('title', 'Topping Prep');
        $this->assertNotNull($category);
        $this->assertSame($section->id, $category->section_id);

        $this->actingAs($admin)->post(route('training.items.store', $category), [
            'title' => 'Explain shelf lives',
            'importance' => 'highly_important',
        ])->assertSessionHasNoErrors();
        $item = ChecklistItem::firstWhere('title', 'Explain shelf lives');
        $this->assertNotNull($item);
        $this->assertNull($item->parent_id);

        // Sub-item under the parent.
        $this->actingAs($admin)->post(route('training.items.store', $category), [
            'title' => 'Sub step',
            'importance' => 'moderately_important',
            'parent_id' => $item->id,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('checklist_items', ['title' => 'Sub step', 'parent_id' => $item->id]);
    }

    public function test_reorder_updates_order_in_bulk(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $a = Section::factory()->create(['order' => 0]);
        $b = Section::factory()->create(['order' => 1]);

        $this->actingAs($admin)->post(route('training.sections.reorder'), [
            'items' => [
                ['id' => $a->id, 'order' => 1],
                ['id' => $b->id, 'order' => 0],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $a->refresh()->order);
        $this->assertSame(0, $b->refresh()->order);
    }

    public function test_item_can_be_created_without_importance(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)->post(route('training.items.store', $category), [
            'title' => 'No importance set',
            'importance' => null,
        ])->assertSessionHasNoErrors();

        $item = ChecklistItem::firstWhere('title', 'No importance set');
        $this->assertNotNull($item);
        $this->assertNull($item->importance);
    }

    public function test_category_color_is_validated(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $section = Section::factory()->create();

        $this->actingAs($admin)->post(route('training.categories.store', $section), [
            'title' => 'Coloured',
            'color' => 'emerald',
        ])->assertSessionHasNoErrors();
        $this->assertSame('emerald', Category::firstWhere('title', 'Coloured')?->color);

        $this->actingAs($admin)->post(route('training.categories.store', $section), [
            'title' => 'Bad colour',
            'color' => 'chartreuse',
        ])->assertSessionHasErrors('color');
    }
}
