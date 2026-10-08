<?php

namespace Database\Factories;

use App\Models\DevelopmentEvaluation;
use App\Models\Trainee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DevelopmentEvaluation>
 */
class DevelopmentEvaluationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trainee_id' => Trainee::factory(),
            'evaluated_by' => null,
            'notes' => null,
            'submitted_at' => now(),
        ];
    }
}
