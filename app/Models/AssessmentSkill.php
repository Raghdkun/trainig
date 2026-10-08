<?php

namespace App\Models;

use Database\Factories\AssessmentSkillFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A station or skill the Development Zone assessment rates (e.g. Making,
 * Cleaning, Communication). Its questions' answers become its star rating.
 * It may link to a Content Builder station so a low rating suggests that
 * station's training content for the development plan.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int|null $section_id
 * @property int $order
 * @property bool $is_active
 * @property-read Section|null $section
 * @property-read Collection<int, AssessmentQuestion> $questions
 */
class AssessmentSkill extends Model
{
    /** @use HasFactory<AssessmentSkillFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['name', 'description', 'section_id', 'order', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Every question for this skill, active and retired, in order.
     *
     * @return HasMany<AssessmentQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(AssessmentQuestion::class)->orderBy('order')->orderBy('id');
    }

    /**
     * @param  Builder<AssessmentSkill>  $query
     * @return Builder<AssessmentSkill>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<AssessmentSkill>  $query
     * @return Builder<AssessmentSkill>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order')->orderBy('id');
    }

    /**
     * Whether any assessment has answered one of this skill's questions.
     */
    public function hasBeenAssessed(): bool
    {
        return DevelopmentEvaluationAnswer::whereIn('assessment_question_id', $this->questions()->select('id'))->exists();
    }
}
