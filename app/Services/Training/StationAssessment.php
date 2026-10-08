<?php

namespace App\Services\Training;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSkill;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

/**
 * The Development Zone assessment: which stations & skills are rated and
 * with which questions, how answers turn into a 0–5 star rating per skill
 * (in ¼-star steps), and which skills count as Development Needs.
 */
class StationAssessment
{
    /** A station/skill rated below this many stars is a Development Need. */
    public const DEVELOPMENT_NEED_BELOW = 3.0;

    /**
     * Active stations & skills that have at least one active question, in
     * order, with those questions loaded.
     *
     * @return Collection<int, AssessmentSkill>
     */
    public function skills(): Collection
    {
        return AssessmentSkill::active()
            ->ordered()
            ->whereHas('questions', fn ($query) => $query->active())
            ->with(['questions' => fn ($query) => $query->active()])
            ->get();
    }

    /**
     * The assessment form as sent to the page.
     *
     * @param  Collection<int, AssessmentSkill>|null  $skills
     * @return list<array{id: int, name: string, description: string|null, questions: list<array{id: int, prompt: string, answer_type: string}>}>
     */
    public function form(?Collection $skills = null): array
    {
        return ($skills ?? $this->skills())->map(fn (AssessmentSkill $skill): array => [
            'id' => $skill->id,
            'name' => $skill->name,
            'description' => $skill->description,
            'questions' => $skill->questions->map(fn (AssessmentQuestion $question): array => [
                'id' => $question->id,
                'prompt' => $question->prompt,
                'answer_type' => $question->answer_type->value,
            ])->values()->all(),
        ])->values()->all();
    }

    /**
     * Validation rules for `answers.{questionId}` — every current question
     * must be answered, within its answer type's range.
     *
     * @param  Collection<int, AssessmentSkill>|null  $skills
     * @return array<string, mixed>
     */
    public function answerRules(?Collection $skills = null): array
    {
        $rules = ['answers' => ['nullable', 'array']];

        foreach (($skills ?? $this->skills()) as $skill) {
            foreach ($skill->questions as $question) {
                $rules["answers.{$question->id}"] = [
                    'required',
                    'integer',
                    "between:{$question->answer_type->minimum()},{$question->answer_type->maximum()}",
                ];
            }
        }

        return $rules;
    }

    /**
     * Turn raw answers into one star rating per station/skill.
     *
     * @param  Collection<int, AssessmentSkill>  $skills
     * @param  array<int, int>  $answers  question id => raw value
     * @return list<array{assessment_skill_id: int, skill_name: string, section_id: int|null, stars: float}>
     */
    public function score(Collection $skills, array $answers): array
    {
        return $skills->map(function (AssessmentSkill $skill) use ($answers): array {
            $shares = $skill->questions
                ->filter(fn (AssessmentQuestion $question): bool => array_key_exists($question->id, $answers))
                ->map(fn (AssessmentQuestion $question): float => $question->answer_type->normalize((int) $answers[$question->id]))
                ->all();

            return [
                'assessment_skill_id' => $skill->id,
                'skill_name' => $skill->name,
                'section_id' => $skill->section_id,
                'stars' => self::stars($shares),
            ];
        })->values()->all();
    }

    /**
     * Average 0–1 shares → 0–5 stars, rounded to the nearest quarter star.
     *
     * @param  array<int, float>  $shares
     */
    public static function stars(array $shares): float
    {
        if ($shares === []) {
            return 0.0;
        }

        $average = array_sum($shares) / count($shares);

        return round($average * 5 * 4) / 4;
    }

    /**
     * Which ratings are Development Needs: every one below 3 stars. If none
     * are, the lowest-rated is still highlighted (unless all are a full 5).
     * Returns the keys of the flagged ratings.
     *
     * @param  iterable<array-key, array{stars: float}>  $scores
     * @return list<array-key>
     */
    public function developmentNeeds(iterable $scores): array
    {
        $scores = BaseCollection::make($scores);

        if ($scores->isEmpty()) {
            return [];
        }

        $below = $scores->filter(fn (array $score): bool => $score['stars'] < self::DEVELOPMENT_NEED_BELOW);

        if ($below->isNotEmpty()) {
            return $below->keys()->all();
        }

        $lowest = $scores->min('stars');

        return $lowest < 5
            ? $scores->filter(fn (array $score): bool => $score['stars'] == $lowest)->keys()->all()
            : [];
    }
}
