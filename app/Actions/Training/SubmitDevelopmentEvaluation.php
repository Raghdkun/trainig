<?php

namespace App\Actions\Training;

use App\Enums\DevelopmentStatus;
use App\Models\DevelopmentEvaluation;
use App\Models\Trainee;
use App\Models\User;
use App\Services\Training\StationAssessment;
use Illuminate\Support\Facades\DB;

class SubmitDevelopmentEvaluation
{
    public function __construct(private readonly StationAssessment $assessment) {}

    /**
     * Record an assessment and store each station/skill's star rating.
     *
     * The manager's first evaluation places the employee in the Development
     * Zone as Pending, where an admin reviews it and builds their plan. A
     * reassessment (training team, later on) only records new ratings to
     * measure improvement — it leaves the workflow status alone.
     *
     * @param  array{notes: string|null, grade?: string|null, points?: int|null, answers: array<int, int>}  $data
     */
    public function handle(Trainee $trainee, User $evaluator, array $data, bool $isReassessment = false): DevelopmentEvaluation
    {
        $skills = $this->assessment->skills();

        return DB::transaction(function () use ($trainee, $evaluator, $data, $isReassessment, $skills): DevelopmentEvaluation {
            $evaluation = DevelopmentEvaluation::create([
                'trainee_id' => $trainee->id,
                'evaluated_by' => $evaluator->id,
                'is_reassessment' => $isReassessment,
                'grade' => $data['grade'] ?? null,
                'points' => $data['points'] ?? null,
                'notes' => $data['notes'],
                'submitted_at' => now(),
            ]);

            $questionIds = $skills->flatMap->questions->pluck('id');

            $evaluation->answers()->createMany(
                $questionIds
                    ->filter(fn (int $id): bool => array_key_exists($id, $data['answers']))
                    ->map(fn (int $id): array => [
                        'assessment_question_id' => $id,
                        'value' => (int) $data['answers'][$id],
                    ])
                    ->values()
                    ->all(),
            );

            $evaluation->skillScores()->createMany(
                $this->assessment->score($skills, $data['answers']),
            );

            if (! $isReassessment) {
                $trainee->update(['development_status' => DevelopmentStatus::Pending]);
            }

            return $evaluation;
        });
    }
}
