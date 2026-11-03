<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $development_evaluation_id
 * @property int $assessment_question_id
 * @property int $value
 * @property-read DevelopmentEvaluation $evaluation
 * @property-read AssessmentQuestion $question
 */
class DevelopmentEvaluationAnswer extends Model
{
    /** @var list<string> */
    protected $fillable = ['development_evaluation_id', 'assessment_question_id', 'value'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<DevelopmentEvaluation, $this>
     */
    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(DevelopmentEvaluation::class, 'development_evaluation_id');
    }

    /**
     * @return BelongsTo<AssessmentQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(AssessmentQuestion::class, 'assessment_question_id');
    }
}
