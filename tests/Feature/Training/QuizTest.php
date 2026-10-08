<?php

namespace Tests\Feature\Training;

use App\Enums\Permission;
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

class QuizTest extends TestCase
{
    use RefreshDatabase;

    // --- Authoring -----------------------------------------------------

    public function test_super_admin_can_add_a_quiz_to_a_section(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $section = Section::factory()->create();

        $this->actingAs($admin)
            ->post(route('training.quizzes.store', $section))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('quizzes', ['section_id' => $section->id]);
    }

    public function test_manager_cannot_manage_quiz_content(): void
    {
        $manager = User::factory()->manager()->create();
        $section = Section::factory()->create();
        $quiz = Quiz::factory()->create(['section_id' => $section->id]);

        $this->actingAs($manager)->post(route('training.quizzes.store', $section))->assertForbidden();
        $this->actingAs($manager)->post(route('training.quiz-questions.store', $quiz), [
            'prompt' => 'x?',
            'options' => ['a', 'b', 'c', 'd'],
            'correct_index' => 0,
        ])->assertForbidden();
    }

    public function test_a_quiz_question_needs_exactly_four_options_and_one_correct_index(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $quiz = Quiz::factory()->create();

        $this->actingAs($admin)->post(route('training.quiz-questions.store', $quiz), [
            'prompt' => 'What comes first?',
            'type' => 'single',
            'options' => ['Wash hands', 'Wear gloves', 'Preheat oven'],
            'correct_index' => 0,
        ])->assertSessionHasErrors('options');

        $this->actingAs($admin)->post(route('training.quiz-questions.store', $quiz), [
            'prompt' => 'What comes first?',
            'type' => 'single',
            'options' => ['Wash hands', 'Wear gloves', 'Preheat oven', 'Clock in'],
            'correct_index' => 0,
        ])->assertSessionHasNoErrors();

        $question = QuizQuestion::firstWhere('prompt', 'What comes first?');
        $this->assertCount(4, $question->options);
        $this->assertSame(1, $question->options()->where('is_correct', true)->count());
        $this->assertTrue($question->options()->orderBy('order')->first()->is_correct);
    }

    public function test_a_multi_choice_question_needs_two_or_three_correct_answers(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $quiz = Quiz::factory()->create();

        $payload = [
            'prompt' => 'Which are cleaning supplies?',
            'type' => 'multi',
            'options' => ['Soap', 'Sponge', 'Pizza cutter', 'Sanitizer'],
        ];

        $this->actingAs($admin)
            ->post(route('training.quiz-questions.store', $quiz), [...$payload, 'correct_indexes' => [0]])
            ->assertSessionHasErrors('correct_indexes');

        $this->actingAs($admin)
            ->post(route('training.quiz-questions.store', $quiz), [...$payload, 'correct_indexes' => [0, 1, 2, 3]])
            ->assertSessionHasErrors('correct_indexes');

        $this->actingAs($admin)
            ->post(route('training.quiz-questions.store', $quiz), [...$payload, 'correct_indexes' => [0, 1, 3]])
            ->assertSessionHasNoErrors();

        $question = QuizQuestion::firstWhere('prompt', 'Which are cleaning supplies?');
        $this->assertSame('multi', $question->type->value);
        $this->assertEqualsCanonicalizing(
            [0, 1, 3],
            $question->options()->where('is_correct', true)->pluck('order')->all(),
        );
    }

    public function test_a_quiz_is_capped_at_five_questions(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $quiz = Quiz::factory()->create();
        QuizQuestion::factory()->count(5)->withOptions()->create(['quiz_id' => $quiz->id]);

        $this->actingAs($admin)
            ->post(route('training.quiz-questions.store', $quiz), [
                'prompt' => 'One too many?',
                'type' => 'single',
                'options' => ['a', 'b', 'c', 'd'],
                'correct_index' => 0,
            ])
            ->assertSessionHasErrors('prompt');

        $this->assertSame(5, $quiz->questions()->count());
    }

    // --- Sending ---------------------------------------------------------

    public function test_super_admin_can_send_a_quiz_and_reuses_the_existing_link(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $trainee = Trainee::factory()->create();
        $quiz = Quiz::factory()->withQuestions()->create();

        $this->actingAs($admin)
            ->post(route('trainees.quiz-attempts.store', $trainee), ['quiz_id' => $quiz->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('quiz_attempts', 1);
        $firstToken = QuizAttempt::sole()->token;

        // Sending again (e.g. re-opening the page) must not invalidate the
        // link already shared with the employee.
        $this->actingAs($admin)
            ->post(route('trainees.quiz-attempts.store', $trainee), ['quiz_id' => $quiz->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('quiz_attempts', 1);
        $this->assertSame($firstToken, QuizAttempt::sole()->token);
    }

    public function test_assigned_manager_cannot_send_a_quiz(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();
        $trainee = Trainee::factory()->forStore($store)->create();
        $trainee->managers()->attach($manager);
        $quiz = Quiz::factory()->withQuestions()->create();

        $this->actingAs($manager)
            ->post(route('trainees.quiz-attempts.store', $trainee), ['quiz_id' => $quiz->id])
            ->assertForbidden();

        $this->assertDatabaseCount('quiz_attempts', 0);
    }

    public function test_a_quiz_with_fewer_than_three_questions_cannot_be_sent(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $trainee = Trainee::factory()->create();
        $quiz = Quiz::factory()->withQuestions(2)->create();

        $this->actingAs($admin)
            ->post(route('trainees.quiz-attempts.store', $trainee), ['quiz_id' => $quiz->id])
            ->assertSessionHasErrors('quiz_id');

        $this->assertDatabaseCount('quiz_attempts', 0);
    }

    public function test_an_archived_trainee_cannot_be_sent_a_quiz(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $trainee = Trainee::factory()->archived()->create();
        $quiz = Quiz::factory()->withQuestions()->create();

        $this->actingAs($admin)
            ->post(route('trainees.quiz-attempts.store', $trainee), ['quiz_id' => $quiz->id])
            ->assertSessionHasErrors('quiz_id');

        $this->assertDatabaseCount('quiz_attempts', 0);
    }

    public function test_unassigned_manager_cannot_send_a_quiz(): void
    {
        $manager = User::factory()->manager()->create();
        $trainee = Trainee::factory()->create();
        $quiz = Quiz::factory()->withQuestions()->create();

        $this->actingAs($manager)
            ->post(route('trainees.quiz-attempts.store', $trainee), ['quiz_id' => $quiz->id])
            ->assertForbidden();
    }

    public function test_manager_granted_the_permission_can_send_a_quiz_to_their_trainee(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->withPermissions(Permission::ShareQuizLinks)->create();
        $trainee = Trainee::factory()->forStore($store)->create();
        $quiz = Quiz::factory()->withQuestions()->create();

        $this->actingAs($manager)
            ->post(route('trainees.quiz-attempts.store', $trainee), ['quiz_id' => $quiz->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('quiz_attempts', 1);
    }

    public function test_the_permission_does_not_reach_trainees_outside_the_managers_stores(): void
    {
        $manager = User::factory()->manager()->withPermissions(Permission::ShareQuizLinks)->create();
        $trainee = Trainee::factory()->create();
        $quiz = Quiz::factory()->withQuestions()->create();

        $this->actingAs($manager)
            ->post(route('trainees.quiz-attempts.store', $trainee), ['quiz_id' => $quiz->id])
            ->assertForbidden();

        $this->assertDatabaseCount('quiz_attempts', 0);
    }

    public function test_the_permission_does_not_allow_sending_to_an_archived_trainee(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->withPermissions(Permission::ShareQuizLinks)->create();
        $trainee = Trainee::factory()->forStore($store)->archived()->create();
        $quiz = Quiz::factory()->withQuestions()->create();

        $this->actingAs($manager)
            ->post(route('trainees.quiz-attempts.store', $trainee), ['quiz_id' => $quiz->id])
            ->assertForbidden();
    }

    // --- Trainee-page status (must never leak the score) -----------------

    public function test_trainee_progress_reports_quiz_status_without_ever_exposing_the_score(): void
    {
        $section = Section::factory()->published()->create();
        $quiz = Quiz::factory()->withQuestions()->create(['section_id' => $section->id]);
        $trainee = Trainee::factory()->create();

        $notSent = app(TraineeProgress::class)->detail($trainee, canShareQuizLinks: true)['sections'][0]['quiz'];
        $this->assertSame($quiz->id, $notSent['id']);
        $this->assertNull($notSent['attempt']);

        $attempt = QuizAttempt::factory()->create(['quiz_id' => $quiz->id, 'trainee_id' => $trainee->id]);
        $sent = app(TraineeProgress::class)->detail($trainee, canShareQuizLinks: true)['sections'][0]['quiz'];
        $this->assertSame('not_started', $sent['attempt']['status']);
        $this->assertStringContainsString($attempt->token, $sent['attempt']['link']);
        $this->assertFalse($sent['attempt']['flagged']);
        $this->assertArrayNotHasKey('score', $sent['attempt']);

        $attempt->update(['started_at' => now()]);
        $started = app(TraineeProgress::class)->detail($trainee, canShareQuizLinks: true)['sections'][0]['quiz'];
        $this->assertSame('in_progress', $started['attempt']['status']);
        $this->assertStringContainsString($attempt->token, $started['attempt']['link']);

        $attempt->update(['completed_at' => now(), 'score' => 100]);
        $completed = app(TraineeProgress::class)->detail($trainee, canShareQuizLinks: true)['sections'][0]['quiz'];
        $this->assertSame('completed', $completed['attempt']['status']);
        $this->assertNull($completed['attempt']['link']);
        $this->assertArrayNotHasKey('score', $completed['attempt']);
    }

    public function test_trainee_progress_omits_the_pending_link_for_viewers_who_cannot_share_it(): void
    {
        $section = Section::factory()->published()->create();
        $quiz = Quiz::factory()->withQuestions()->create(['section_id' => $section->id]);
        $trainee = Trainee::factory()->create();
        QuizAttempt::factory()->create(['quiz_id' => $quiz->id, 'trainee_id' => $trainee->id]);

        $sent = app(TraineeProgress::class)->detail($trainee)['sections'][0]['quiz'];

        $this->assertSame('not_started', $sent['attempt']['status']);
        $this->assertNull($sent['attempt']['link']);
    }

    public function test_trainee_page_shows_the_quiz_link_to_super_admins_only(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();
        $admin = User::factory()->superAdmin()->create();
        $trainee = Trainee::factory()->forStore($store)->create();
        $section = Section::factory()->published()->create();
        $quiz = Quiz::factory()->withQuestions()->create(['section_id' => $section->id]);
        $attempt = QuizAttempt::factory()->create(['quiz_id' => $quiz->id, 'trainee_id' => $trainee->id]);

        $this->actingAs($manager)
            ->get(route('trainees.show', $trainee))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('canShareQuizLinks', false)
                ->where('progress.sections.0.quiz.attempt.status', 'not_started')
                ->where('progress.sections.0.quiz.attempt.link', null)
            );

        $this->actingAs($admin)
            ->get(route('trainees.show', $trainee))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('canShareQuizLinks', true)
                ->where('progress.sections.0.quiz.attempt.link', route('quiz.show', $attempt->token))
            );
    }

    public function test_trainee_page_shows_the_quiz_link_to_a_manager_granted_the_permission(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->withPermissions(Permission::ShareQuizLinks)->create();
        $trainee = Trainee::factory()->forStore($store)->create();
        $section = Section::factory()->published()->create();
        $quiz = Quiz::factory()->withQuestions()->create(['section_id' => $section->id]);
        $attempt = QuizAttempt::factory()->create(['quiz_id' => $quiz->id, 'trainee_id' => $trainee->id]);

        $this->actingAs($manager)
            ->get(route('trainees.show', $trainee))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('canShareQuizLinks', true)
                ->where('progress.sections.0.quiz.attempt.link', route('quiz.show', $attempt->token))
            );
    }

    // --- Public quiz page --------------------------------------------------

    public function test_public_quiz_page_shows_questions_for_a_pending_token(): void
    {
        $this->withoutVite();
        $quiz = Quiz::factory()->withQuestions(3)->create();
        $trainee = Trainee::factory()->create(['name' => 'Sam Crew']);
        $attempt = QuizAttempt::factory()->create(['quiz_id' => $quiz->id, 'trainee_id' => $trainee->id]);

        $this->get(route('quiz.show', $attempt->token))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('quiz/show')
                ->where('traineeName', 'Sam Crew')
                ->where('completed', false)
                ->has('questions', 3)
                ->has('questions.0.options', 4)
            );
    }

    public function test_public_quiz_page_hides_questions_once_completed(): void
    {
        $this->withoutVite();
        $quiz = Quiz::factory()->withQuestions()->create();
        $attempt = QuizAttempt::factory()->completed()->create(['quiz_id' => $quiz->id]);

        $this->get(route('quiz.show', $attempt->token))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('completed', true)
                ->has('questions', 0)
            );
    }

    public function test_a_pending_quiz_never_reveals_correct_answers_or_explanations(): void
    {
        $this->withoutVite();
        $quiz = Quiz::factory()->withQuestions(3)->create();
        $quiz->questions()->update(['explanation' => 'Secret reasoning']);
        $attempt = QuizAttempt::factory()->create(['quiz_id' => $quiz->id]);

        $response = $this->get(route('quiz.show', $attempt->token))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('result', null)
                ->missing('questions.0.explanation')
                ->missing('questions.0.options.0.is_correct')
            );

        $this->assertStringNotContainsString('Secret reasoning', $response->getContent());
        $this->assertStringNotContainsString('is_correct', $response->getContent());
    }

    public function test_after_submitting_the_trainee_sees_their_score_mistakes_and_the_correct_answers(): void
    {
        $this->withoutVite();
        $quiz = Quiz::factory()->withQuestions(2)->create();
        $attempt = QuizAttempt::factory()->create(['quiz_id' => $quiz->id]);
        $questions = $quiz->questions()->with('options')->get();
        $questions[1]->update(['explanation' => 'Raw chicken goes on the bottom shelf.']);

        $right = $questions[0]->options->firstWhere('is_correct', true);
        $wrong = $questions[1]->options->firstWhere('is_correct', false);
        $missedCorrect = $questions[1]->options->firstWhere('is_correct', true);

        $this->post(route('quiz.store', $attempt->token), ['answers' => [
            $questions[0]->id => [$right->id],
            $questions[1]->id => [$wrong->id],
        ]])->assertRedirect(route('quiz.show', $attempt->token));

        $this->get(route('quiz.show', $attempt->token))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('completed', true)
                ->where('result.score', 50)
                ->where('result.correct_count', 1)
                ->where('result.questions_count', 2)
                ->where('result.questions.0.is_correct', true)
                ->where('result.questions.1.is_correct', false)
                ->where('result.questions.1.explanation', 'Raw chicken goes on the bottom shelf.')
                ->where('result.questions.1.options', fn ($options) => collect($options)->contains(
                    fn ($option) => $option['id'] === $wrong->id && $option['is_chosen'] && ! $option['is_correct'],
                ) && collect($options)->contains(
                    fn ($option) => $option['id'] === $missedCorrect->id && $option['is_correct'] && ! $option['is_chosen'],
                ))
            );
    }

    public function test_confirming_the_results_were_reviewed_closes_the_link(): void
    {
        $this->withoutVite();
        $quiz = Quiz::factory()->withQuestions(3)->create();
        $attempt = QuizAttempt::factory()->completed(67)->create(['quiz_id' => $quiz->id]);

        $this->get(route('quiz.show', $attempt->token))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('closed', false)
                ->where('result.score', 67)
            );

        $this->post(route('quiz.close', $attempt->token))
            ->assertRedirect(route('quiz.show', $attempt->token));

        $this->assertNotNull($attempt->fresh()->results_reviewed_at);

        // The results and correct answers can't be reopened from the link.
        $this->get(route('quiz.show', $attempt->token))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('completed', true)
                ->where('closed', true)
                ->where('result', null)
                ->has('questions', 0)
            );

        // The score itself is untouched for the training team.
        $this->assertSame(67, $attempt->fresh()->score);
    }

    public function test_closing_twice_keeps_the_first_review_time(): void
    {
        $attempt = QuizAttempt::factory()->completed()->create(['results_reviewed_at' => now()->subDay()]);
        $firstReview = $attempt->results_reviewed_at->toIso8601String();

        $this->post(route('quiz.close', $attempt->token))->assertRedirect();

        $this->assertSame($firstReview, $attempt->fresh()->results_reviewed_at->toIso8601String());
    }

    public function test_an_unsubmitted_quiz_cannot_be_closed(): void
    {
        $attempt = QuizAttempt::factory()->create();

        $this->post(route('quiz.close', $attempt->token))->assertNotFound();
        $this->post(route('quiz.close', 'not-a-real-token'))->assertNotFound();

        $this->assertNull($attempt->fresh()->results_reviewed_at);
    }

    public function test_quiz_result_details_show_when_the_trainee_reviewed_their_results(): void
    {
        $this->withoutVite();
        $attempt = QuizAttempt::factory()->completed()->create(['results_reviewed_at' => now()]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('training.quiz-results.show', $attempt))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('attempt.results_reviewed_at', $attempt->results_reviewed_at->toIso8601String())
            );
    }

    public function test_super_admin_can_save_an_explanation_with_a_question(): void
    {
        $quiz = Quiz::factory()->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('training.quiz-questions.store', $quiz), [
                'prompt' => 'Where does raw chicken go?',
                'explanation' => 'Always store raw chicken on the bottom shelf.',
                'type' => 'single',
                'options' => ['Top', 'Middle', 'Bottom', 'Door'],
                'correct_index' => 2,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Always store raw chicken on the bottom shelf.', $quiz->questions()->sole()->explanation);
    }

    public function test_an_explanation_is_optional_and_limited_in_length(): void
    {
        $quiz = Quiz::factory()->create();
        $admin = User::factory()->superAdmin()->create();
        $payload = [
            'prompt' => 'Question?',
            'type' => 'single',
            'options' => ['A', 'B', 'C', 'D'],
            'correct_index' => 0,
        ];

        $this->actingAs($admin)
            ->post(route('training.quiz-questions.store', $quiz), $payload)
            ->assertSessionHasNoErrors();
        $this->assertNull($quiz->questions()->sole()->explanation);

        $this->actingAs($admin)
            ->post(route('training.quiz-questions.store', $quiz), [...$payload, 'explanation' => str_repeat('x', 1001)])
            ->assertSessionHasErrors('explanation');
    }

    public function test_an_unknown_token_404s(): void
    {
        $this->get(route('quiz.show', 'not-a-real-token'))->assertNotFound();
    }

    // --- Wrong-recipient safety net ----------------------------------------

    public function test_public_quiz_page_reports_whether_it_was_flagged_as_a_mismatch(): void
    {
        $this->withoutVite();
        $quiz = Quiz::factory()->withQuestions()->create();
        $attempt = QuizAttempt::factory()->create(['quiz_id' => $quiz->id]);

        $this->get(route('quiz.show', $attempt->token))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('flaggedAsMismatch', false));
    }

    public function test_reporting_a_mismatch_flags_the_attempt_without_completing_it(): void
    {
        $this->withoutVite();
        $quiz = Quiz::factory()->withQuestions()->create();
        $attempt = QuizAttempt::factory()->create(['quiz_id' => $quiz->id]);

        $this->post(route('quiz.report-mismatch', $attempt->token))
            ->assertRedirect(route('quiz.show', $attempt->token));

        $attempt->refresh();
        $this->assertTrue($attempt->isFlaggedAsMisdirected());
        $this->assertFalse($attempt->isCompleted());

        // The link stays valid — whoever it was actually meant for can still
        // use it (identity gate re-runs on the frontend, not blocked here).
        $this->get(route('quiz.show', $attempt->token))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('flaggedAsMismatch', true));
    }

    public function test_flagged_attempt_can_still_be_submitted_by_the_intended_trainee(): void
    {
        $quiz = Quiz::factory()->withQuestions(1)->create();
        $attempt = QuizAttempt::factory()->create(['quiz_id' => $quiz->id]);
        $question = $quiz->questions()->with('options')->first();

        $this->post(route('quiz.report-mismatch', $attempt->token));

        $this->post(route('quiz.store', $attempt->token), [
            'answers' => [$question->id => [$question->options->first()->id]],
        ])->assertRedirect(route('quiz.show', $attempt->token));

        $this->assertTrue($attempt->fresh()->isCompleted());
    }

    public function test_an_unknown_token_404s_when_reporting_a_mismatch(): void
    {
        $this->post(route('quiz.report-mismatch', 'not-a-real-token'))->assertNotFound();
    }

    // --- Started tracking ----------------------------------------------------

    public function test_just_opening_the_link_does_not_mark_the_quiz_as_started(): void
    {
        $this->withoutVite();
        $quiz = Quiz::factory()->withQuestions()->create();
        $attempt = QuizAttempt::factory()->create(['quiz_id' => $quiz->id]);

        // Chat apps fetch links to build previews — a GET must never count.
        $this->get(route('quiz.show', $attempt->token))->assertOk();

        $this->assertNull($attempt->fresh()->started_at);
    }

    public function test_confirming_identity_marks_the_quiz_as_started_once(): void
    {
        $quiz = Quiz::factory()->withQuestions()->create();
        $attempt = QuizAttempt::factory()->create(['quiz_id' => $quiz->id]);

        $this->post(route('quiz.start', $attempt->token))
            ->assertRedirect(route('quiz.show', $attempt->token));

        $startedAt = $attempt->fresh()->started_at;
        $this->assertNotNull($startedAt);
        $this->assertSame('in_progress', $attempt->fresh()->status()->value);

        // Re-opening later keeps the original start time.
        $this->travel(2)->hours();
        $this->post(route('quiz.start', $attempt->token));

        $this->assertTrue($startedAt->equalTo($attempt->fresh()->started_at));
    }

    public function test_starting_a_completed_quiz_changes_nothing(): void
    {
        $quiz = Quiz::factory()->withQuestions()->create();
        $attempt = QuizAttempt::factory()->completed()->create([
            'quiz_id' => $quiz->id,
            'started_at' => null,
        ]);

        $this->post(route('quiz.start', $attempt->token));

        $this->assertNull($attempt->fresh()->started_at);
        $this->assertSame('completed', $attempt->fresh()->status()->value);
    }

    public function test_an_unknown_token_404s_when_starting(): void
    {
        $this->post(route('quiz.start', 'not-a-real-token'))->assertNotFound();
    }

    public function test_submitting_the_quiz_scores_it_and_locks_the_link(): void
    {
        $quiz = Quiz::factory()->withQuestions(2)->create();
        $attempt = QuizAttempt::factory()->create(['quiz_id' => $quiz->id]);
        $questions = $quiz->questions()->with('options')->get();

        // Answer the first question correctly, the second incorrectly.
        $answers = [
            $questions[0]->id => [$questions[0]->options->firstWhere('is_correct', true)->id],
            $questions[1]->id => [$questions[1]->options->firstWhere('is_correct', false)->id],
        ];

        $this->post(route('quiz.store', $attempt->token), ['answers' => $answers])
            ->assertRedirect(route('quiz.show', $attempt->token));

        $attempt->refresh();
        $this->assertTrue($attempt->isCompleted());
        $this->assertSame(50, $attempt->score);
        $this->assertDatabaseCount('quiz_answers', 2);
        // Submitting without a recorded start still leaves a full timeline.
        $this->assertNotNull($attempt->started_at);

        // The link is now inert — no re-submitting over a scored attempt.
        $this->post(route('quiz.store', $attempt->token), $answers)->assertNotFound();
    }

    public function test_submitting_rejects_an_option_that_belongs_to_a_different_question(): void
    {
        $quiz = Quiz::factory()->withQuestions(2)->create();
        $attempt = QuizAttempt::factory()->create(['quiz_id' => $quiz->id]);
        $questions = $quiz->questions()->with('options')->get();

        $this->post(route('quiz.store', $attempt->token), [
            'answers' => [
                // Swapped: question 0 answered with question 1's option id.
                $questions[0]->id => [$questions[1]->options->first()->id],
                $questions[1]->id => [$questions[1]->options->first()->id],
            ],
        ])->assertSessionHasErrors("answers.{$questions[0]->id}.0");

        $this->assertFalse($attempt->fresh()->isCompleted());
    }

    public function test_a_single_answer_question_rejects_more_than_one_selection(): void
    {
        $quiz = Quiz::factory()->withQuestions(1)->create();
        $attempt = QuizAttempt::factory()->create(['quiz_id' => $quiz->id]);
        $question = $quiz->questions()->with('options')->first();

        $this->post(route('quiz.store', $attempt->token), [
            'answers' => [$question->id => $question->options->pluck('id')->take(2)->all()],
        ])->assertSessionHasErrors("answers.{$question->id}");

        $this->assertFalse($attempt->fresh()->isCompleted());
    }

    public function test_a_multi_choice_question_only_scores_correct_on_an_exact_match(): void
    {
        $quiz = Quiz::factory()->create();
        $question = QuizQuestion::factory()->withOptions([0, 1])->create(['quiz_id' => $quiz->id]);
        $options = $question->options()->orderBy('order')->get();
        $attempt = QuizAttempt::factory()->create(['quiz_id' => $quiz->id]);

        // Missing one of the two correct options — not an exact match.
        $this->post(route('quiz.store', $attempt->token), [
            'answers' => [$question->id => [$options[0]->id]],
        ])->assertRedirect(route('quiz.show', $attempt->token));

        $this->assertSame(0, $attempt->refresh()->score);
        $this->assertDatabaseCount('quiz_answers', 1);
    }

    public function test_a_multi_choice_question_scores_correct_when_the_full_set_is_selected(): void
    {
        $quiz = Quiz::factory()->create();
        $question = QuizQuestion::factory()->withOptions([0, 1])->create(['quiz_id' => $quiz->id]);
        $options = $question->options()->orderBy('order')->get();
        $attempt = QuizAttempt::factory()->create(['quiz_id' => $quiz->id]);

        $this->post(route('quiz.store', $attempt->token), [
            'answers' => [$question->id => [$options[0]->id, $options[1]->id]],
        ])->assertRedirect(route('quiz.show', $attempt->token));

        $this->assertSame(100, $attempt->refresh()->score);
        $this->assertDatabaseCount('quiz_answers', 2);
    }

    // --- Quiz Results (training team only) --------------------------------

    public function test_manager_cannot_view_quiz_results(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->get(route('training.quiz-results.index'))->assertForbidden();
    }

    public function test_super_admin_sees_full_answers_and_score_in_quiz_results(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create();
        $quiz = Quiz::factory()->withQuestions(1)->create();
        $trainee = Trainee::factory()->create(['name' => 'Jordan Lee']);
        $attempt = QuizAttempt::factory()->completed(75)->create([
            'quiz_id' => $quiz->id,
            'trainee_id' => $trainee->id,
        ]);
        $question = $quiz->questions->first();
        $chosen = $question->options->firstWhere('is_correct', false);
        $attempt->answers()->create([
            'quiz_question_id' => $question->id,
            'quiz_question_option_id' => $chosen->id,
        ]);

        $this->actingAs($admin)
            ->get(route('training.quiz-results.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('attempts', 1)
                ->where('attempts.0.trainee.name', 'Jordan Lee')
                ->where('attempts.0.score', 75)
            );

        $this->actingAs($admin)
            ->get(route('training.quiz-results.show', $attempt))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('attempt.score', 75)
                ->where('attempt.status', 'completed')
                ->where('attempt.link', null)
                ->where('attempt.correct_count', 0)
                ->where('attempt.questions_count', 1)
                ->where('questions.0.is_correct', false)
                ->has('questions.0.options', 4)
                ->where('questions.0.options', fn ($options) => collect($options)
                    ->firstWhere('id', $chosen->id)['is_chosen'] === true)
            );
    }

    public function test_quiz_results_distinguish_not_started_in_progress_and_completed(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create();
        $quiz = Quiz::factory()->withQuestions()->create();
        $notStarted = QuizAttempt::factory()->create(['quiz_id' => $quiz->id, 'sent_at' => now()->subDays(3)]);
        $inProgress = QuizAttempt::factory()->started()->create(['quiz_id' => $quiz->id, 'sent_at' => now()->subDays(2)]);
        QuizAttempt::factory()->completed(80)->create(['quiz_id' => $quiz->id, 'sent_at' => now()->subDay()]);

        $this->actingAs($admin)
            ->get(route('training.quiz-results.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('attempts', 3)
                // Newest first by sent date.
                ->where('attempts.0.status', 'completed')
                ->where('attempts.0.link', null)
                ->where('attempts.1.status', 'in_progress')
                ->where('attempts.1.link', route('quiz.show', $inProgress->token))
                ->whereNot('attempts.1.started_at', null)
                ->where('attempts.2.status', 'not_started')
                ->where('attempts.2.link', route('quiz.show', $notStarted->token))
                ->where('attempts.2.started_at', null)
            );
    }

    public function test_quiz_result_details_for_an_unsubmitted_attempt_offer_the_link(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create();
        $quiz = Quiz::factory()->withQuestions()->create();
        $attempt = QuizAttempt::factory()->started()->create(['quiz_id' => $quiz->id]);

        $this->actingAs($admin)
            ->get(route('training.quiz-results.show', $attempt))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('attempt.status', 'in_progress')
                ->where('attempt.link', route('quiz.show', $attempt->token))
                ->where('attempt.score', null)
                ->where('attempt.correct_count', null)
            );
    }

    public function test_quiz_results_surfaces_attempts_flagged_as_sent_to_the_wrong_person(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create();
        $quiz = Quiz::factory()->withQuestions()->create();
        $attempt = QuizAttempt::factory()->create(['quiz_id' => $quiz->id]);
        $attempt->update(['wrong_recipient_reported_at' => now()]);

        $this->actingAs($admin)
            ->get(route('training.quiz-results.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('attempts.0.flagged', true)
            );
    }

    public function test_resetting_an_attempt_allows_sending_the_quiz_again(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $quiz = Quiz::factory()->withQuestions()->create();
        $attempt = QuizAttempt::factory()->completed()->create(['quiz_id' => $quiz->id]);

        $this->actingAs($admin)
            ->delete(route('training.quiz-results.destroy', $attempt))
            ->assertRedirect(route('training.quiz-results.index'));

        $this->assertDatabaseMissing('quiz_attempts', ['id' => $attempt->id]);
    }
}
