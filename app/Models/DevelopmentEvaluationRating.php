<?php

namespace App\Models;

use Database\Factories\DevelopmentEvaluationRatingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $development_evaluation_id
 * @property int $development_evaluation_criterion_id
 * @property int $rating
 * @property-read DevelopmentEvaluation $evaluation
 * @property-read DevelopmentEvaluationCriterion $criterion
 */
class DevelopmentEvaluationRating extends Model
{
    /** @use HasFactory<DevelopmentEvaluationRatingFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'development_evaluation_id', 'development_evaluation_criterion_id', 'rating',
    ];

    /**
     * @return BelongsTo<DevelopmentEvaluation, $this>
     */
    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(DevelopmentEvaluation::class, 'development_evaluation_id');
    }

    /**
     * @return BelongsTo<DevelopmentEvaluationCriterion, $this>
     */
    public function criterion(): BelongsTo
    {
        return $this->belongsTo(DevelopmentEvaluationCriterion::class, 'development_evaluation_criterion_id');
    }
}
