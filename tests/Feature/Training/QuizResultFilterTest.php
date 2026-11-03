<?php

namespace Tests\Feature\Training;

use App\Models\QuizAttempt;
use App\Models\Store;
use App\Models\Trainee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class QuizResultFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_quiz_results_list_every_store_by_default(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $storeA = Store::factory()->create(['name' => 'Alpha']);
        $storeB = Store::factory()->create(['name' => 'Bravo']);
        QuizAttempt::factory()->for(Trainee::factory()->forStore($storeA))->completed(90)->create();
        QuizAttempt::factory()->for(Trainee::factory()->forStore($storeB))->create();

        $this->actingAs($admin)
            ->get(route('training.quiz-results.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('attempts', 2)
                ->has('stores', 2)
                ->where('stores.0.name', 'Alpha')
                ->where('filters.store', null)
            );
    }

    public function test_quiz_results_can_be_filtered_by_store(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();
        $inStoreA = QuizAttempt::factory()->for(Trainee::factory()->forStore($storeA))->completed(55)->create();
        QuizAttempt::factory()->for(Trainee::factory()->forStore($storeB))->completed(95)->create();

        $this->actingAs($admin)
            ->get(route('training.quiz-results.index', ['store' => $storeA->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('attempts', 1)
                ->where('attempts.0.id', $inStoreA->id)
                ->where('attempts.0.store.id', $storeA->id)
                ->where('filters.store', $storeA->id)
            );
    }

    public function test_a_store_with_no_quiz_links_returns_an_empty_list(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $emptyStore = Store::factory()->create();
        QuizAttempt::factory()->create();

        $this->actingAs($admin)
            ->get(route('training.quiz-results.index', ['store' => $emptyStore->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('attempts', 0)
                ->where('filters.store', $emptyStore->id)
            );
    }

    public function test_managers_cannot_open_quiz_results_even_with_a_store_filter(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();

        $this->actingAs($manager)
            ->get(route('training.quiz-results.index', ['store' => $store->id]))
            ->assertForbidden();
    }
}
