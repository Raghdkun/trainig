<?php

namespace Tests\Feature\Training;

use App\Models\Category;
use App\Models\ChecklistItem;
use App\Models\Evaluation;
use App\Models\Section;
use App\Models\Store;
use App\Models\Trainee;
use App\Models\User;
use App\Services\Training\ReportAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function analytics(): ReportAnalytics
    {
        return app(ReportAnalytics::class);
    }

    /**
     * @param  array<int, int|null>  $ratings  keyed by desired order; null = completed without a score
     * @return array<int, ChecklistItem>
     */
    private function items(int $count, ?Category $category = null): array
    {
        $category ??= Category::factory()->create();

        return collect(range(0, $count - 1))
            ->map(fn (int $order): ChecklistItem => ChecklistItem::factory()->create([
                'category_id' => $category->id,
                'order' => $order,
            ]))
            ->all();
    }

    private function evaluate(
        Trainee $trainee,
        ChecklistItem $item,
        ?int $rating = null,
        bool $completed = true,
        ?string $completedAt = null,
        ?User $by = null,
    ): void {
        Evaluation::factory()->create([
            'trainee_id' => $trainee->id,
            'checklist_item_id' => $item->id,
            'completed' => $completed,
            'rating' => $rating,
            'evaluated_by' => $by?->id,
            'completed_at' => $completed ? ($completedAt ?? now()) : null,
        ]);
    }

    public function test_overview_reports_headline_kpis(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $store = Store::factory()->create();
        $trainee = Trainee::factory()->forStore($store)->create();
        [$a, $b] = $this->items(2);

        $this->evaluate($trainee, $a, rating: 80);   // completed + scored
        // $b left incomplete

        $overview = $this->analytics()->overview($this->analytics()->for($admin));

        $this->assertSame(1, $overview['trainees']);
        $this->assertSame(50, $overview['completion']);          // 1 of 2 leaves
        $this->assertSame(80.0, $overview['average_score']);
        $this->assertSame(0, $overview['fully_trained']);
        $this->assertSame(1, $overview['in_progress']);
        $this->assertSame(1, $overview['evaluations_recorded']);
    }

    public function test_completion_trend_buckets_by_week(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $store = Store::factory()->create();
        $trainee = Trainee::factory()->forStore($store)->create();
        [$a, $b, $c] = $this->items(3);

        $this->evaluate($trainee, $a, completedAt: now()->toDateTimeString());
        $this->evaluate($trainee, $b, completedAt: now()->toDateTimeString());
        $this->evaluate($trainee, $c, completedAt: now()->subWeek()->toDateTimeString());

        $trend = $this->analytics()->completionTrend($this->analytics()->for($admin));

        $this->assertCount(12, $trend);
        $this->assertSame(2, $trend[11]['count']);  // this week
        $this->assertSame(1, $trend[10]['count']);  // last week
    }

    public function test_score_distribution_counts_bands(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $store = Store::factory()->create();
        $trainee = Trainee::factory()->forStore($store)->create();
        $items = $this->items(6);

        foreach ([10, 20, 30, 55, 80, 95] as $i => $rating) {
            $this->evaluate($trainee, $items[$i], rating: $rating);
        }

        $bands = $this->analytics()->scoreDistribution($this->analytics()->for($admin));

        $this->assertSame(2, $bands[0]['count']);  // 0–20 (10, 20)
        $this->assertSame(1, $bands[1]['count']);  // 21–40 (30)
        $this->assertSame(1, $bands[2]['count']);  // 41–60 (55)
        $this->assertSame(1, $bands[3]['count']);  // 61–80 (80)
        $this->assertSame(1, $bands[4]['count']);  // 81–100 (95)
    }

    public function test_store_performance_ranks_by_completion(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $storeA = Store::factory()->create(['name' => 'Alpha']);
        $storeB = Store::factory()->create(['name' => 'Beta']);
        [$a, $b] = $this->items(2);

        $traineeA = Trainee::factory()->forStore($storeA)->create();
        $this->evaluate($traineeA, $a, rating: 90);
        $this->evaluate($traineeA, $b, rating: 90);   // A fully complete

        $traineeB = Trainee::factory()->forStore($storeB)->create();
        $this->evaluate($traineeB, $a, rating: 40);   // B half complete

        $rows = $this->analytics()->storePerformance($this->analytics()->for($admin));

        $this->assertCount(2, $rows);
        $this->assertSame('Alpha', $rows[0]['name']);     // ranked first (100%)
        $this->assertSame(100, $rows[0]['completion']);
        $this->assertSame(90.0, $rows[0]['average_score']);
        $this->assertSame(50, $rows[1]['completion']);    // Beta 1 of 2
    }

    public function test_manager_activity_counts_recorded_evaluations(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();
        $trainee = Trainee::factory()->forStore($store)->create();
        $trainee->managers()->attach($manager);
        [$a, $b] = $this->items(2);

        $this->evaluate($trainee, $a, rating: 60, by: $manager);
        $this->evaluate($trainee, $b, rating: 80, by: $manager);

        $rows = $this->analytics()->managerActivity($this->analytics()->for($admin));
        $row = collect($rows)->firstWhere('id', $manager->id);

        $this->assertNotNull($row);
        $this->assertSame(1, $row['assigned_trainees']);
        $this->assertSame(2, $row['evaluations_recorded']);
        $this->assertSame(70.0, $row['average_score']);
    }

    public function test_trainee_status_classifies_each_bucket(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $store = Store::factory()->create();
        [$a, $b] = $this->items(2);

        $done = Trainee::factory()->forStore($store)->create(['name' => 'Done']);
        $this->evaluate($done, $a, rating: 90);
        $this->evaluate($done, $b, rating: 90);

        $progressing = Trainee::factory()->forStore($store)->create(['name' => 'Progressing']);
        $this->evaluate($progressing, $a, rating: 85);

        $risky = Trainee::factory()->forStore($store)->create(['name' => 'Risky']);
        $this->evaluate($risky, $a, rating: 20);   // low score → at risk

        Trainee::factory()->forStore($store)->create(['name' => 'Fresh']);   // untouched, recent

        $status = $this->analytics()->traineeStatus($this->analytics()->for($admin));
        $byName = collect($status['rows'])->keyBy('name');

        $this->assertSame('completed', $byName['Done']['status']);
        $this->assertSame('in_progress', $byName['Progressing']['status']);
        $this->assertSame('at_risk', $byName['Risky']['status']);
        $this->assertSame('not_started', $byName['Fresh']['status']);
    }

    public function test_stalled_trainee_is_at_risk(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $store = Store::factory()->create();
        [$a, $b] = $this->items(2);

        $stalled = Trainee::factory()->forStore($store)->create(['name' => 'Stalled']);
        $this->evaluate($stalled, $a, rating: 90, completedAt: now()->subDays(30)->toDateTimeString());

        $status = $this->analytics()->traineeStatus($this->analytics()->for($admin));
        $row = collect($status['rows'])->firstWhere('name', 'Stalled');

        $this->assertSame('at_risk', $row['status']);   // good score but no activity in 30 days
    }

    public function test_station_insights_surface_problem_items(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $store = Store::factory()->create();
        $section = Section::factory()->create();
        $category = Category::factory()->create(['section_id' => $section->id]);
        [$strong, $weak] = $this->items(2, $category);
        $trainee = Trainee::factory()->forStore($store)->create();

        $this->evaluate($trainee, $strong, rating: 95);
        $this->evaluate($trainee, $weak, rating: 15);

        $insights = $this->analytics()->stationInsights($this->analytics()->for($admin));

        $this->assertSame($section->id, $insights['sections'][0]['id']);
        $this->assertSame(55.0, $insights['sections'][0]['average_score']);   // (95 + 15) / 2
        $this->assertSame(100, $insights['sections'][0]['completion']);
        $this->assertSame($weak->id, $insights['problem_items'][0]['id']);     // lowest first
        $this->assertSame(15.0, $insights['problem_items'][0]['average_score']);
    }

    public function test_importance_breakdown_groups_by_level(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $store = Store::factory()->create();
        $category = Category::factory()->create();
        $critical = ChecklistItem::factory()->create(['category_id' => $category->id, 'importance' => 'highly_important']);
        $minor = ChecklistItem::factory()->create(['category_id' => $category->id, 'importance' => 'optional']);
        $trainee = Trainee::factory()->forStore($store)->create();

        $this->evaluate($trainee, $critical, rating: 40);
        $this->evaluate($trainee, $minor, rating: 100);

        $rows = collect($this->analytics()->importanceBreakdown($this->analytics()->for($admin)))->keyBy('importance');

        $this->assertSame(40.0, $rows['highly_important']['average_score']);
        $this->assertSame(100.0, $rows['optional']['average_score']);
    }

    public function test_manager_scope_only_sees_assigned_trainees(): void
    {
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();
        $manager = User::factory()->manager($storeA)->create();
        [$a] = $this->items(1);

        $mine = Trainee::factory()->forStore($storeA)->create();
        $mine->managers()->attach($manager);
        $this->evaluate($mine, $a, rating: 70);

        $other = Trainee::factory()->forStore($storeB)->create();
        $this->evaluate($other, $a, rating: 10);

        $overview = $this->analytics()->overview($this->analytics()->for($manager));

        $this->assertSame(1, $overview['trainees']);         // only the assigned trainee
        $this->assertSame(70.0, $overview['average_score']); // other store excluded
    }

    public function test_super_admin_can_filter_by_store(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();
        [$a] = $this->items(1);

        $ta = Trainee::factory()->forStore($storeA)->create();
        $this->evaluate($ta, $a, rating: 70);
        $tb = Trainee::factory()->forStore($storeB)->create();
        $this->evaluate($tb, $a, rating: 30);

        $filtered = $this->analytics()->overview($this->analytics()->for($admin, ['store' => $storeB->id]));

        $this->assertSame(1, $filtered['trainees']);
        $this->assertSame(30.0, $filtered['average_score']);
    }
}
