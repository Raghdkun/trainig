<?php

namespace App\Models;

use App\Enums\AssessmentAnswerType;
use Database\Factories\AssessmentQuestionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One Development Zone assessment question for a station or skill. Answers
 * to a skill's questions combine into that skill's star rating.
 *
 * @property int $id
 * @property int $assessment_skill_id
 * @property string $prompt
 * @property AssessmentAnswerType $answer_type
 * @property int $order
 * @property bool $is_active
 * @property-read AssessmentSkill $skill
 */
class AssessmentQuestion extends Model
{
    /** @use HasFactory<AssessmentQuestionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['assessment_skill_id', 'prompt', 'answer_type', 'order', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answer_type' => AssessmentAnswerType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<AssessmentSkill, $this>
     */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(AssessmentSkill::class, 'assessment_skill_id');
    }

    /**
     * @return HasMany<DevelopmentEvaluationAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(DevelopmentEvaluationAnswer::class);
    }

    /**
     * @param  Builder<AssessmentQuestion>  $query
     * @return Builder<AssessmentQuestion>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<AssessmentQuestion>  $query
     * @return Builder<AssessmentQuestion>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order')->orderBy('id');
    }
}
