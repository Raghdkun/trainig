<?php

namespace App\Models;

use App\Enums\EvaluationGrade;
use Database\Factories\DevelopmentEvaluationFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $trainee_id
 * @property int|null $evaluated_by
 * @property bool $is_reassessment
 * @property EvaluationGrade|null $grade
 * @property int|null $points
 * @property string|null $notes
 * @property Carbon $submitted_at
 * @property-read Trainee $trainee
 * @property-read User|null $evaluator
 * @property-read Collection<int, DevelopmentEvaluationRating> $ratings
 * @property-read Collection<int, DevelopmentEvaluationAnswer> $answers
 * @property-read Collection<int, DevelopmentSkillScore> $skillScores
 */
class DevelopmentEvaluation extends Model
{
    /** @use HasFactory<DevelopmentEvaluationFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'trainee_id', 'evaluated_by', 'is_reassessment', 'grade', 'points', 'notes', 'submitted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'is_reassessment' => 'boolean',
            'grade' => EvaluationGrade::class,
            'points' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Trainee, $this>
     */
    public function trainee(): BelongsTo
    {
        return $this->belongsTo(Trainee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    /**
     * @return HasMany<DevelopmentEvaluationRating, $this>
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(DevelopmentEvaluationRating::class);
    }

    /**
     * Raw answers to the station assessment questions.
     *
     * @return HasMany<DevelopmentEvaluationAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(DevelopmentEvaluationAnswer::class);
    }

    /**
     * Each station/skill's star rating from this evaluation, in skill order.
     *
     * @return HasMany<DevelopmentSkillScore, $this>
     */
    public function skillScores(): HasMany
    {
        return $this->hasMany(DevelopmentSkillScore::class)->orderBy('id');
    }
}
