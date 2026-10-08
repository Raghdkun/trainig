<?php

namespace Tests\Feature\Training;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Section;
use App\Models\Store;
use App\Models\Trainee;
use App\Models\User;
use App\Services\Training\TraineeProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * A sent quiz version is never changed: edits create the next version, old
 * links keep serving the version they were sent for, and past answers and
 * scores stay exactly as recorded.
 */
class QuizVersioningTest extends TestCase
{
    use RefreshDatabase;

    // --- Editing ---------------------------------------------------------

    public function test_editing_a_quiz_that_was_never_sent_edits_it_in_place(): void
    {
        $quiz = $this->quiz();
        $question = $quiz->questions()->first();

        $this->actingAs($this->admin())
            ->put(route('training.quiz-questions.update', $question), $this->questionPayload('Reworded?'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('quizzes', 1);
        $this->assertSame('Reworded?', $question->fresh()->prompt);
        $this->assertNull($quiz->fresh()->retired_at);
    }

    public function test_editing_a_sent_quiz_creates_version_two_and_leaves_version_one_untouched(): void
    {
        $v1 = $this->quiz();
        QuizAttempt::factory()->create(['quiz_id' => $v1->id]);
        $question = $v1->questions()->with('options')->first();
        $originalPrompt = $question->prompt;
        $originalOptions = $question->options->map->only(['id', 'text', 'is_correct'])->all();

        $this->actingAs($this->admin())
            ->put(route('training.quiz-questions.update', $question), $this->questionPayload('Reworded?'))
            ->assertSessionHasNoErrors();

        // v1 is frozen exactly as it was sent, and retired.
        $question->refresh()->load('options');
        $this->assertSame($originalPrompt, $question->prompt);
        $this->assertSame($originalOptions, $question->options->map->only(['id', 'text', 'is_correct'])->all());
        $this->assertNotNull($v1->fresh()->retired_at);
        $this->assertSame(3, $v1->questions()->count());

        // v2 is the live version, with the edit applied to its copy.
        $v2 = $v1->section->fresh()->quiz;
        $this->assertSame(2, $v2->version);
        $this->assertSame(3, $v2->questions()->count());
        $this->assertTrue($v2->questions()->where('prompt', 'Reworded?')->exists());
        $this->assertSame(1, $v2->questions()->where('prompt', 'Reworded?')->count());
    }

    public function test_explanations_carry_over_to_the_next_version(): void
    {
        $v1 = $this->quiz();
        $kept = $v1->questions()->orderBy('order')->first();
        $kept->update(['explanation' => 'Because food safety.']);
        $edited = $v1->questions()->orderBy('order')->skip(1)->first();
        QuizAttempt::factory()->create(['quiz_id' => $v1->id]);

        $this->actingAs($this->admin())
            ->put(route('training.quiz-questions.update', $edited), [
                ...$this->questionPayload('Reworded?'),
                'explanation' => 'New reasoning.',
            ])
            ->assertSessionHasNoErrors();

        $v2 = Quiz::where('version', 2)->sole();
        $this->assertSame('Because food safety.', $v2->questions()->where('prompt', $kept->prompt)->sole()->explanation);
        $this->assertSame('New reasoning.', $v2->questions()->where('prompt', 'Reworded?')->sole()->explanation);
        $this->assertNull($edited->fresh()->explanation);
    }

    public function test_a_run_of_edits_after_sending_creates_only_one_new_version(): void
    {
        $v1 = $this->quiz();
        QuizAttempt::factory()->create(['quiz_id' => $v1->id]);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('training.quiz-questions.store', $v1), $this->questionPayload('Added?'))
            ->assertSessionHasNoErrors();

        $v2 = $v1->section->fresh()->quiz;
        $this->actingAs($admin)
            ->delete(route('training.quiz-questions.destroy', $v2->questions()->first()))
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)
            ->put(route('training.quiz-questions.update', $v2->questions()->first()), $this->questionPayload('Tweaked?'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('quizzes', 2);
        $this->assertSame(3, $v1->questions()->count());
        $this->assertSame(3, $v2->questions()->count());
        $this->assertSame($v2->id, $v1->section->fresh()->quiz->id);
    }

    public function test_adding_or_deleting_a_question_on_a_sent_quiz_also_versions_it(): void
    {
        $admin = $this->admin();

        $added = $this->quiz();
        QuizAttempt::factory()->create(['quiz_id' => $added->id]);
        $this->actingAs($admin)->post(route('training.quiz-questions.store', $added), $this->questionPayload('New?'));
        $this->assertSame(3, $added->questions()->count());
        $this->assertSame(4, $added->section->fresh()->quiz->questions()->count());

        $deleted = $this->quiz();
        QuizAttempt::factory()->create(['quiz_id' => $deleted->id]);
        $this->actingAs($admin)->delete(route('training.quiz-questions.destroy', $deleted->questions()->first()));
        $this->assertSame(3, $deleted->questions()->count());
        $this->assertSame(2, $deleted->section->fresh()->quiz->questions()->count());
    }

    public function test_editing_a_retired_version_is_refused(): void
    {
        $v1 = $this->quiz();
        QuizAttempt::factory()->create(['quiz_id' => $v1->id]);
        $staleQuestion = $v1->questions()->first();
        $admin = $this->admin();

        // First edit retires v1; a second edit from the same stale page must not
        // silently fork another version or touch v1.
        $this->actingAs($admin)->put(route('training.quiz-questions.update', $staleQuestion), $this->questionPayload('First?'));
        $this->actingAs($admin)
            ->put(route('training.quiz-questions.update', $staleQuestion), $this->questionPayload('Second?'))
            ->assertSessionHasErrors('prompt');

        $this->assertDatabaseCount('quizzes', 2);
        $this->assertNotSame('Second?', $staleQuestion->fresh()->prompt);
    }

    // --- Old links keep their version ------------------------------------

    public function test_a_pending_v1_link_still_shows_and_scores_against_v1_after_an_edit(): void
    {
        $v1 = $this->quiz();
        $attempt = QuizAttempt::factory()->create(['quiz_id' => $v1->id]);
        $v1Questions = $v1->questions()->with('options')->get();

        $this->actingAs($this->admin())
            ->put(route('training.quiz-questions.update', $v1Questions->first()), $this->questionPayload('Changed in v2?'));

        $this->get(route('quiz.show', $attempt->token))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('questions', 3)
                ->where('questions.0.id', $v1Questions->first()->id)
                ->where('questions.0.prompt', $v1Questions->first()->prompt)
            );

        $answers = $v1Questions->mapWithKeys(fn (QuizQuestion $question): array => [
            $question->id => [$question->options->firstWhere('is_correct', true)->id],
        ])->all();

        $this->post(route('quiz.store', $attempt->token), ['answers' => $answers])
            ->assertRedirect(route('quiz.show', $attempt->token));

        $this->assertSame(100, $attempt->fresh()->score);
    }

    public function test_a_completed_result_is_unchanged_by_later_edits(): void
    {
        $v1 = $this->quiz();
        $attempt = QuizAttempt::factory()->completed(67)->create(['quiz_id' => $v1->id]);
        $question = $v1->questions()->with('options')->first();
        $chosen = $question->options->firstWhere('is_correct', false);
        $attempt->answers()->create([
            'quiz_question_id' => $question->id,
            'quiz_question_option_id' => $chosen->id,
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('training.quiz-questions.update', $question), $this->questionPayload('Rewritten?'));
        $this->actingAs($admin)->delete(route('training.quiz-questions.destroy', $v1->section->fresh()->quiz->questions()->first()));

        $attempt->refresh();
        $this->assertSame(67, $attempt->score);
        $this->assertDatabaseHas('quiz_answers', [
            'quiz_attempt_id' => $attempt->id,
            'quiz_question_id' => $question->id,
            'quiz_question_option_id' => $chosen->id,
        ]);

        $this->actingAs($admin)
            ->get(route('training.quiz-results.show', $attempt))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('attempt.version', 1)
                ->where('attempt.score', 67)
                ->has('questions', 3)
                ->where('questions.0.prompt', $question->prompt)
                ->where('questions.0.options', fn ($options) => collect($options)->firstWhere('id', $chosen->id)['is_chosen'] === true)
            );
    }

    public function test_a_completed_v1_link_never_becomes_active_again(): void
    {
        $v1 = $this->quiz();
        $attempt = QuizAttempt::factory()->completed()->create(['quiz_id' => $v1->id]);
        $question = $v1->questions()->with('options')->first();

        $this->actingAs($this->admin())
            ->put(route('training.quiz-questions.update', $question), $this->questionPayload('New wording?'));

        $this->get(route('quiz.show', $attempt->token))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('completed', true)
                ->has('questions', 0)
            );

        $this->post(route('quiz.store', $attempt->token), [
            'answers' => [$question->id => [$question->options->first()->id]],
        ])->assertNotFound();
    }

    // --- Sending new versions --------------------------------------------

    public function test_super_admin_can_send_v2_to_a_trainee_who_completed_v1(): void
    {
        $v1 = $this->quiz();
        $trainee = Trainee::factory()->create();
        $v1Attempt = QuizAttempt::factory()->completed(80)->create(['quiz_id' => $v1->id, 'trainee_id' => $trainee->id]);
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('training.quiz-questions.update', $v1->questions()->first()), $this->questionPayload('v2 wording?'));
        $v2 = $v1->section->fresh()->quiz;

        $this->actingAs($admin)
            ->post(route('trainees.quiz-attempts.store', $trainee), ['quiz_id' => $v2->id])
            ->assertSessionHasNoErrors();

        $v2Attempt = QuizAttempt::where('quiz_id', $v2->id)->sole();
        $this->assertSame($trainee->id, $v2Attempt->trainee_id);
        $this->assertNotSame($v1Attempt->token, $v2Attempt->token);
        $this->assertFalse($v2Attempt->isCompleted());

        $v1Attempt->refresh();
        $this->assertTrue($v1Attempt->isCompleted());
        $this->assertSame(80, $v1Attempt->score);
    }

    public function test_a_retired_version_cannot_be_sent(): void
    {
        $retired = $this->quiz(['retired_at' => now()]);
        $trainee = Trainee::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('trainees.quiz-attempts.store', $trainee), ['quiz_id' => $retired->id])
            ->assertSessionHasErrors('quiz_id');

        $this->assertDatabaseCount('quiz_attempts', 0);
    }

    public function test_a_manager_cannot_send_a_new_version(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();
        $trainee = Trainee::factory()->forStore($store)->create();
        $v1 = $this->quiz();
        QuizAttempt::factory()->completed()->create(['quiz_id' => $v1->id, 'trainee_id' => $trainee->id]);
        $v2 = $this->quiz(['section_id' => $v1->section_id, 'version' => 2]);
        $v1->update(['retired_at' => now()]);

        $this->actingAs($manager)
            ->post(route('trainees.quiz-attempts.store', $trainee), ['quiz_id' => $v2->id])
            ->assertForbidden();
    }

    public function test_trainee_page_flags_when_the_latest_link_is_for_an_older_version(): void
    {
        $section = Section::factory()->published()->create();
        $v1 = $this->quiz(['section_id' => $section->id]);
        $trainee = Trainee::factory()->create();
        QuizAttempt::factory()->completed()->create(['quiz_id' => $v1->id, 'trainee_id' => $trainee->id]);

        $status = fn (): array => app(TraineeProgress::class)->detail($trainee, canShareQuizLinks: true)['sections'][0]['quiz'];

        $this->assertFalse($status()['is_outdated']);
        $this->assertSame(1, $status()['attempt']['version']);

        $this->actingAs($this->admin())->put(route('training.quiz-questions.update', $v1->questions()->first()), $this->questionPayload('Edited?'));
        $v2 = $section->fresh()->quiz;

        $afterEdit = $status();
        $this->assertSame($v2->id, $afterEdit['id']);
        $this->assertSame(2, $afterEdit['version']);
        $this->assertTrue($afterEdit['is_outdated']);
        $this->assertSame('completed', $afterEdit['attempt']['status']);
        $this->assertSame(1, $afterEdit['attempt']['version']);

        QuizAttempt::factory()->create(['quiz_id' => $v2->id, 'trainee_id' => $trainee->id]);

        $afterResend = $status();
        $this->assertFalse($afterResend['is_outdated']);
        $this->assertSame('not_started', $afterResend['attempt']['status']);
        $this->assertSame(2, $afterResend['attempt']['version']);
    }

    // --- Removing ----------------------------------------------------------

    public function test_removing_a_sent_quiz_retires_it_and_keeps_its_results(): void
    {
        $quiz = $this->quiz();
        $attempt = QuizAttempt::factory()->completed(90)->create(['quiz_id' => $quiz->id]);

        $this->actingAs($this->admin())
            ->delete(route('training.quizzes.destroy', $quiz))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($quiz->fresh()->retired_at);
        $this->assertNull($quiz->section->fresh()->quiz);
        $this->assertSame(90, $attempt->fresh()->score);

        $this->actingAs($this->admin())
            ->get(route('training.quiz-results.show', $attempt))
            ->assertOk();
    }

    public function test_removing_a_never_sent_quiz_deletes_it(): void
    {
        $quiz = $this->quiz();

        $this->actingAs($this->admin())->delete(route('training.quizzes.destroy', $quiz));

        $this->assertModelMissing($quiz);
        $this->assertDatabaseCount('quiz_questions', 0);
    }

    public function test_re_adding_a_removed_quiz_starts_the_next_version_empty(): void
    {
        $quiz = $this->quiz();
        QuizAttempt::factory()->create(['quiz_id' => $quiz->id]);
        $admin = $this->admin();

        $this->actingAs($admin)->delete(route('training.quizzes.destroy', $quiz));
        $this->actingAs($admin)
            ->post(route('training.quizzes.store', $quiz->section))
            ->assertSessionHasNoErrors();

        $live = $quiz->section->fresh()->quiz;
        $this->assertSame(2, $live->version);
        $this->assertSame(0, $live->questions()->count());

        // Adding again while a live version exists is still a no-op.
        $this->actingAs($admin)->post(route('training.quizzes.store', $quiz->section));
        $this->assertDatabaseCount('quizzes', 2);
    }

    public function test_the_builder_shows_the_live_version_and_how_often_it_was_sent(): void
    {
        $quiz = $this->quiz();
        QuizAttempt::factory()->count(2)->create(['quiz_id' => $quiz->id]);

        $this->actingAs($this->admin())
            ->get(route('training.sections.edit', $quiz->section))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('section.quiz.id', $quiz->id)
                ->where('section.quiz.version', 1)
                ->where('section.quiz.attempts_count', 2)
            );
    }

    private function admin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function quiz(array $attributes = []): Quiz
    {
        return Quiz::factory()->withQuestions(3)->create($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    private function questionPayload(string $prompt): array
    {
        return [
            'prompt' => $prompt,
            'type' => 'single',
            'options' => ['Alpha', 'Bravo', 'Charlie', 'Delta'],
            'correct_index' => 1,
        ];
    }
}
