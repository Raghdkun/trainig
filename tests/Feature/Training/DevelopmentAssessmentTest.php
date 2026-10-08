<?php

namespace Tests\Feature\Training;

use App\Enums\AssessmentAnswerType;
use App\Enums\DevelopmentStatus;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentSkill;
use App\Models\DevelopmentEvaluation;
use App\Models\Section;
use App\Models\Store;
use App\Models\Trainee;
use App\Models\User;
use App\Services\Training\ReportAnalytics;
use App\Services\Training\StationAssessment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The Development Zone station assessment: authoring questions per station,
 * scoring answers into ¼-star ratings, Development Needs, and reassessment.
 */
class DevelopmentAssessmentTest extends TestCase
{
    use RefreshDatabase;

    // --- Scoring ---------------------------------------------------------

    public function test_answers_normalize_by_type(): void
    {
        $this->assertSame(1.0, AssessmentAnswerType::YesNo->normalize(1));
        $this->assertSame(0.0, AssessmentAnswerType::YesNo->normalize(0));
        $this->assertSame(0.6, AssessmentAnswerType::Level->normalize(3));
        $this->assertSame(0.85, AssessmentAnswerType::Percentage->normalize(85));
    }

    public function test_station_stars_round_to_the_nearest_quarter(): void
    {
        $this->assertSame(3.5, StationAssessment::stars([1.0, 0.4]));   // 70% → 3.5
        $this->assertSame(3.75, StationAssessment::stars([0.73]));      // 3.65 → 3.75
        $this->assertSame(2.25, StationAssessment::stars([0.45]));      // 2.25 exactly
        $this->assertSame(5.0, StationAssessment::stars([1.0, 1.0]));
        $this->assertSame(0.0, StationAssessment::stars([]));
    }

    public function test_skills_below_three_stars_are_development_needs(): void
    {
        $needs = app(StationAssessment::class)->developmentNeeds([
            'making' => ['stars' => 2.75],
            'cleaning' => ['stars' => 4.0],
            'communication' => ['stars' => 1.5],
        ]);

        $this->assertSame(['making', 'communication'], $needs);
    }

    public function test_without_any_skill_below_three_the_lowest_is_still_flagged(): void
    {
        $assessment = app(StationAssessment::class);

        $this->assertSame(['cleaning'], $assessment->developmentNeeds([
            'making' => ['stars' => 4.5],
            'cleaning' => ['stars' => 3.25],
        ]));

        // Nothing to develop when everything is perfect.
        $this->assertSame([], $assessment->developmentNeeds([
            'making' => ['stars' => 5.0],
        ]));
    }

    // --- Assessment setup ------------------------------------------------

    public function test_super_admin_manages_stations_and_skills_with_an_optional_content_link(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $making = Section::factory()->create(['title' => 'Pizza Dress (Making)']);

        // A skill that isn't a Content Builder station at all.
        $this->actingAs($admin)
            ->post(route('training.assessment-skills.store'), [
                'name' => 'Communication',
                'description' => 'Talks clearly with the team and customers.',
            ])
            ->assertSessionHasNoErrors();

        // A station linked to its training content.
        $this->actingAs($admin)
            ->post(route('training.assessment-skills.store'), [
                'name' => 'Making Station',
                'section_id' => $making->id,
            ])
            ->assertSessionHasNoErrors();

        $communication = AssessmentSkill::where('name', 'Communication')->sole();
        $this->assertNull($communication->section_id);
        $this->assertSame($making->id, AssessmentSkill::where('name', 'Making Station')->sole()->section_id);

        $this->actingAs($admin)
            ->put(route('training.assessment-skills.update', $communication), [
                'name' => 'Communication',
                'is_active' => false,
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($communication->fresh()->is_active);
    }

    public function test_super_admin_can_add_edit_and_remove_questions(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $skill = AssessmentSkill::factory()->create();

        $this->actingAs($admin)
            ->post(route('training.assessment-questions.store', $skill), [
                'prompt' => 'Can they stretch dough to size?',
                'answer_type' => 'yes_no',
            ])
            ->assertSessionHasNoErrors();

        $question = $skill->questions()->sole();
        $this->assertSame(AssessmentAnswerType::YesNo, $question->answer_type);

        $this->actingAs($admin)
            ->put(route('training.assessment-questions.update', $question), [
                'prompt' => 'How well do they stretch dough?',
                'answer_type' => 'level',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('How well do they stretch dough?', $question->fresh()->prompt);
        $this->assertSame(AssessmentAnswerType::Level, $question->fresh()->answer_type);

        $this->actingAs($admin)
            ->delete(route('training.assessment-questions.destroy', $question))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($question);
    }

    public function test_answered_questions_and_assessed_skills_are_retired_not_deleted(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $question = AssessmentQuestion::factory()->create();
        $evaluation = DevelopmentEvaluation::factory()->create();
        $evaluation->answers()->create(['assessment_question_id' => $question->id, 'value' => 1]);

        $this->actingAs($admin)->delete(route('training.assessment-questions.destroy', $question));
        $this->assertFalse($question->fresh()->is_active);

        $this->actingAs($admin)->delete(route('training.assessment-skills.destroy', $question->skill));
        $this->assertFalse($question->skill->fresh()->is_active);
        $this->assertDatabaseCount('development_evaluation_answers', 1);

        // A skill nobody has been assessed on is deleted with its questions.
        $unused = AssessmentQuestion::factory()->create();
        $this->actingAs($admin)->delete(route('training.assessment-skills.destroy', $unused->skill));
        $this->assertModelMissing($unused->skill);
        $this->assertModelMissing($unused);
    }

    public function test_setup_validation(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $skill = AssessmentSkill::factory()->create();

        $this->actingAs($admin)
            ->post(route('training.assessment-skills.store'), ['name' => '', 'section_id' => 999999])
            ->assertSessionHasErrors(['name', 'section_id']);

        $this->actingAs($admin)
            ->post(route('training.assessment-questions.store', $skill), ['prompt' => '', 'answer_type' => 'essay'])
            ->assertSessionHasErrors(['prompt', 'answer_type']);
    }

    public function test_managers_cannot_open_or_change_the_assessment_setup(): void
    {
        $manager = User::factory()->manager()->create();
        $skill = AssessmentSkill::factory()->create();

        $this->actingAs($manager)->get(route('training.assessment-setup'))->assertForbidden();
        $this->actingAs($manager)->post(route('training.assessment-skills.store'), ['name' => 'Sneaky'])->assertForbidden();
        $this->actingAs($manager)
            ->post(route('training.assessment-questions.store', $skill), ['prompt' => 'Sneaky?', 'answer_type' => 'yes_no'])
            ->assertForbidden();
    }

    public function test_the_setup_page_lists_skills_with_their_active_questions(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create();
        $making = Section::factory()->create(['title' => 'Pizza Dress (Making)']);
        $skill = AssessmentSkill::factory()->linkedTo($making)->create(['name' => 'Making Station']);
        $active = AssessmentQuestion::factory()->create(['assessment_skill_id' => $skill->id]);
        AssessmentQuestion::factory()->inactive()->create(['assessment_skill_id' => $skill->id]);
        AssessmentSkill::factory()->inactive()->create(['name' => 'Old skill']);

        $this->actingAs($admin)
            ->get(route('training.assessment-setup'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('training/development-zone/assessment-setup')
                ->has('skills', 2)
                ->where('skills.0.name', 'Making Station')
                ->where('skills.0.section.title', 'Pizza Dress (Making)')
                ->has('skills.0.questions', 1)
                ->where('skills.0.questions.0.id', $active->id)
                ->where('skills.1.is_active', false)
                ->has('answerTypeOptions', 3)
                ->has('sectionOptions')
            );
    }

    // --- Reassessment ----------------------------------------------------

    /**
     * An Active trainee with a baseline evaluation: Making 1.75★ (linked to a
     * content station), Cleaning 4★ (a skill with no content station).
     *
     * @return array{0: Trainee, 1: AssessmentSkill, 2: AssessmentQuestion, 3: AssessmentSkill, 4: AssessmentQuestion}
     */
    private function assessedTrainee(): array
    {
        $store = Store::factory()->create();
        $trainee = Trainee::factory()->forStore($store)->developmentStatus(DevelopmentStatus::Active)->create();
        $makingStation = Section::factory()->create(['title' => 'Pizza Dress (Making)']);
        $making = AssessmentSkill::factory()->linkedTo($makingStation)->create(['name' => 'Making', 'order' => 1]);
        $makingQuestion = AssessmentQuestion::factory()->percentage()->create(['assessment_skill_id' => $making->id]);
        $cutting = AssessmentSkill::factory()->create(['name' => 'Cleaning', 'order' => 2]);
        $cuttingQuestion = AssessmentQuestion::factory()->level()->create(['assessment_skill_id' => $cutting->id]);

        $baseline = DevelopmentEvaluation::factory()->create([
            'trainee_id' => $trainee->id,
            'grade' => 'C',
            'points' => 55,
            'submitted_at' => now()->subWeeks(3),
        ]);
        $baseline->skillScores()->createMany([
            ['assessment_skill_id' => $making->id, 'skill_name' => 'Making', 'section_id' => $makingStation->id, 'stars' => 1.75],
            ['assessment_skill_id' => $cutting->id, 'skill_name' => 'Cleaning', 'section_id' => null, 'stars' => 4.0],
        ]);

        return [$trainee, $making, $makingQuestion, $cutting, $cuttingQuestion];
    }

    public function test_admin_reassesses_without_changing_the_workflow_status(): void
    {
        $admin = User::factory()->superAdmin()->create();
        [$trainee, $making, $makingQuestion, , $cuttingQuestion] = $this->assessedTrainee();

        $this->actingAs($admin)
            ->post(route('trainees.development.reassess', $trainee), [
                'notes' => 'Much better on the make line.',
                'answers' => [$makingQuestion->id => 80, $cuttingQuestion->id => 4],
            ])
            ->assertRedirect(route('development-zone.show', $trainee));

        $reassessment = $trainee->developmentEvaluations()->where('is_reassessment', true)->sole();
        $this->assertSame($admin->id, $reassessment->evaluated_by);
        $this->assertNull($reassessment->grade);
        $this->assertSame(4.0, $reassessment->skillScores()->where('assessment_skill_id', $making->id)->sole()->stars);
        $this->assertSame(DevelopmentStatus::Active, $trainee->fresh()->development_status);
    }

    public function test_the_detail_page_compares_before_and_after_and_flags_development_needs(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create();
        [$trainee, $making, $makingQuestion, $cutting, $cuttingQuestion] = $this->assessedTrainee();

        // Before reassessing: Making (1.75★) is a Development Need.
        $this->actingAs($admin)
            ->get(route('development-zone.show', $trainee))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('evaluation.grade', 'C')
                ->has('skillRatings', 2)
                ->where('skillRatings.0.name', 'Making')
                ->where('skillRatings.0.section_id', $making->section_id)
                ->where('skillRatings.0.stars', 1.75)
                ->where('skillRatings.0.baseline_stars', null)
                ->where('skillRatings.0.is_need', true)
                ->where('skillRatings.1.name', 'Cleaning')
                ->where('skillRatings.1.section_id', null)
                ->where('skillRatings.1.is_need', false)
                ->has('assessmentHistory', 1)
                ->where('canReassess', true)
            );

        $this->actingAs($admin)->post(route('trainees.development.reassess', $trainee), [
            'answers' => [$makingQuestion->id => 80, $cuttingQuestion->id => 4],
        ]);

        // After: Making 1.75★ → 4★, Cleaning 4★ → 4★; the original evaluation
        // card still shows the manager's grade.
        $this->actingAs($admin)
            ->get(route('development-zone.show', $trainee))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('evaluation.grade', 'C')
                ->where('skillRatings.0.skill_id', $making->id)
                ->where('skillRatings.0.stars', 4)
                ->where('skillRatings.0.baseline_stars', 1.75)
                ->where('skillRatings.1.skill_id', $cutting->id)
                ->where('skillRatings.1.baseline_stars', 4)
                ->has('assessmentHistory', 2)
                ->where('assessmentHistory.0.is_reassessment', false)
                ->where('assessmentHistory.1.is_reassessment', true)
            );
    }

    public function test_managers_cannot_reassess(): void
    {
        $this->withoutVite();
        [$trainee, , $makingQuestion, , $cuttingQuestion] = $this->assessedTrainee();
        $manager = User::factory()->manager($trainee->store)->create();

        $this->actingAs($manager)
            ->get(route('development-zone.show', $trainee))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canReassess', false));

        $this->actingAs($manager)->get(route('development-zone.reassess', $trainee))->assertForbidden();
        $this->actingAs($manager)
            ->post(route('trainees.development.reassess', $trainee), [
                'answers' => [$makingQuestion->id => 80, $cuttingQuestion->id => 4],
            ])
            ->assertForbidden();

        $this->assertSame(0, $trainee->developmentEvaluations()->where('is_reassessment', true)->count());
    }

    public function test_a_pending_trainee_cannot_be_reassessed_yet(): void
    {
        $admin = User::factory()->superAdmin()->create();
        [$trainee, , $makingQuestion, , $cuttingQuestion] = $this->assessedTrainee();
        $trainee->update(['development_status' => DevelopmentStatus::Pending]);

        $this->actingAs($admin)
            ->post(route('trainees.development.reassess', $trainee), [
                'answers' => [$makingQuestion->id => 80, $cuttingQuestion->id => 4],
            ])
            ->assertSessionHasErrors('answers');

        $this->actingAs($admin)
            ->get(route('development-zone.reassess', $trainee))
            ->assertRedirect(route('development-zone.show', $trainee));
    }

    public function test_reassessment_needs_every_question_answered(): void
    {
        $admin = User::factory()->superAdmin()->create();
        [$trainee, , $makingQuestion, , $cuttingQuestion] = $this->assessedTrainee();

        $this->actingAs($admin)
            ->post(route('trainees.development.reassess', $trainee), [
                'answers' => [$makingQuestion->id => 80],
            ])
            ->assertSessionHasErrors("answers.{$cuttingQuestion->id}");
    }

    public function test_the_reassess_page_shows_each_skills_previous_rating(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create();
        [$trainee, $making, , $cutting] = $this->assessedTrainee();

        $this->actingAs($admin)
            ->get(route('development-zone.reassess', $trainee))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('training/development-zone/reassess')
                ->has('skills', 2)
                ->where("previousStars.{$making->id}", 1.75)
                ->where("previousStars.{$cutting->id}", 4)
            );
    }

    // --- Reports ---------------------------------------------------------

    public function test_reports_average_the_station_stars(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->assessedTrainee(); // 1.75★ and 4★

        $summary = app(ReportAnalytics::class)->developmentZone(app(ReportAnalytics::class)->for($admin));

        $this->assertSame(2.9, $summary['average_evaluation_rating']);
    }
}
