<?php

namespace Tests\Feature\Training;

use App\Models\Category;
use App\Models\ChecklistItem;
use App\Models\Evaluation;
use App\Models\Store;
use App\Models\Trainee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guests_cannot_view_reports(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
    }

    public function test_managers_and_admins_can_open_the_hub(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('reports/index')
                ->has('overview')
                ->where('isSuperAdmin', true)
                ->has('weekOptions')
            );
    }

    public function test_the_hub_combines_the_selected_stores(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();
        $storeC = Store::factory()->create();
        Trainee::factory()->forStore($storeA)->create();
        Trainee::factory()->count(2)->forStore($storeB)->create();
        Trainee::factory()->forStore($storeC)->create();

        $this->actingAs($admin)
            ->get(route('reports.index', ['stores' => [$storeA->id, $storeB->id]]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.stores', [$storeA->id, $storeB->id])
                ->where('overview.trainees', 3)
            );

        // A single store from the sidebar switcher (or an older link) still works.
        $this->actingAs($admin)
            ->get(route('reports.index', ['store' => $storeC->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.stores', [$storeC->id])
                ->where('overview.trainees', 1)
            );

        $this->actingAs($admin)
            ->get(route('reports.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.stores', [])
                ->where('overview.trainees', 4)
            );
    }

    public function test_csv_export_respects_the_selected_stores(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();
        $storeC = Store::factory()->create();
        Trainee::factory()->forStore($storeA)->create(['name' => 'Alpha Trainee']);
        Trainee::factory()->forStore($storeB)->create(['name' => 'Bravo Trainee']);
        Trainee::factory()->forStore($storeC)->create(['name' => 'Charlie Trainee']);

        $content = $this->actingAs($admin)
            ->get(route('reports.export', [
                'format' => 'csv',
                'report' => 'trainees',
                'stores' => [$storeA->id, $storeB->id],
            ]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Alpha Trainee', $content);
        $this->assertStringContainsString('Bravo Trainee', $content);
        $this->assertStringNotContainsString('Charlie Trainee', $content);
    }

    public function test_csv_export_streams_trainee_rows(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $store = Store::factory()->create();
        Trainee::factory()->forStore($store)->create(['name' => 'Casey Crew']);

        $response = $this->actingAs($admin)
            ->get(route('reports.export', ['format' => 'csv', 'report' => 'trainees']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('Trainee', $content);
        $this->assertStringContainsString('Casey Crew', $content);
    }

    public function test_pdf_export_downloads(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)
            ->get(route('reports.export', ['format' => 'pdf']));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
    }

    public function test_manager_export_is_scoped_to_assigned_trainees(): void
    {
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();
        $manager = User::factory()->manager($storeA)->create();

        $mine = Trainee::factory()->forStore($storeA)->create(['name' => 'Mine Trainee']);
        $mine->managers()->attach($manager);
        Trainee::factory()->forStore($storeB)->create(['name' => 'Other Trainee']);

        $content = $this->actingAs($manager)
            ->get(route('reports.export', ['format' => 'csv', 'report' => 'trainees']))
            ->streamedContent();

        $this->assertStringContainsString('Mine Trainee', $content);
        $this->assertStringNotContainsString('Other Trainee', $content);
    }

    public function test_invalid_format_is_rejected(): void
    {
        $admin = User::factory()->superAdmin()->create();

        // Evaluation ensures there is at least one row to consider.
        $store = Store::factory()->create();
        $trainee = Trainee::factory()->forStore($store)->create();
        $item = ChecklistItem::factory()->create(['category_id' => Category::factory()->create()->id]);
        Evaluation::factory()->create([
            'trainee_id' => $trainee->id,
            'checklist_item_id' => $item->id,
            'rating' => 50,
        ]);

        $this->actingAs($admin)
            ->get(route('reports.export', ['format' => 'xml']))
            ->assertSessionHasErrors('format');
    }
}
