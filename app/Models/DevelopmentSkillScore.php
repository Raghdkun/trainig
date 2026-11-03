<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A station/skill's star rating (0–5, ¼-star steps) from one evaluation,
 * frozen at the time it was submitted — including the skill's name and the
 * content station it was linked to then.
 *
 * @property int $id
 * @property int $development_evaluation_id
 * @property int|null $assessment_skill_id
 * @property string $skill_name
 * @property int|null $section_id
 * @property float $stars
 * @property-read DevelopmentEvaluation $evaluation
 * @property-read AssessmentSkill|null $skill
 * @property-read Section|null $section
 */
class DevelopmentSkillScore extends Model
{
    /** @var list<string> */
    protected $fillable = ['development_evaluation_id', 'assessment_skill_id', 'skill_name', 'section_id', 'stars'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stars' => 'float',
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
     * @return BelongsTo<AssessmentSkill, $this>
     */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(AssessmentSkill::class, 'assessment_skill_id');
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
