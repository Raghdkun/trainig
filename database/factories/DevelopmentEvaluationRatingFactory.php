<?php

namespace Database\Factories;

use App\Models\DevelopmentEvaluation;
use App\Models\DevelopmentEvaluationCriterion;
use App\Models\DevelopmentEvaluationRating;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DevelopmentEvaluationRating>
 */
class DevelopmentEvaluationRatingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'development_evaluation_id' => DevelopmentEvaluation::factory(),
            'development_evaluation_criterion_id' => DevelopmentEvaluationCriterion::factory(),
            'rating' => fake()->numberBetween(1, 5),
        ];
    }
}
