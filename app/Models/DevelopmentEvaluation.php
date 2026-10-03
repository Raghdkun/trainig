<?php

namespace App\Models;

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
 * @property string|null $notes
 * @property Carbon $submitted_at
 * @property-read Trainee $trainee
 * @property-read User|null $evaluator
 * @property-read Collection<int, DevelopmentEvaluationRating> $ratings
 */
class DevelopmentEvaluation extends Model
{
    /** @use HasFactory<DevelopmentEvaluationFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'trainee_id', 'evaluated_by', 'notes', 'submitted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
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
}
