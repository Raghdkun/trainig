<?php

namespace App\Models;

use Database\Factories\DevelopmentEvaluationCriterionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $label
 * @property string|null $description
 * @property int $order
 * @property bool $is_active
 * @property-read Collection<int, DevelopmentEvaluationRating> $ratings
 */
class DevelopmentEvaluationCriterion extends Model
{
    /** @use HasFactory<DevelopmentEvaluationCriterionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'label', 'description', 'order', 'is_active',
    ];

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
     * @return HasMany<DevelopmentEvaluationRating, $this>
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(DevelopmentEvaluationRating::class);
    }

    /**
     * @param  Builder<DevelopmentEvaluationCriterion>  $query
     * @return Builder<DevelopmentEvaluationCriterion>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<DevelopmentEvaluationCriterion>  $query
     * @return Builder<DevelopmentEvaluationCriterion>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order');
    }
}
