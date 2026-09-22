<?php

namespace App\Models;

use Database\Factories\QuizFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A short comprehension check attached to one station. Optional — a Section
 * has at most one.
 *
 * @property int $id
 * @property int $section_id
 * @property-read Section $section
 * @property-read Collection<int, QuizQuestion> $questions
 * @property-read Collection<int, QuizAttempt> $attempts
 */
class Quiz extends Model
{
    /** @use HasFactory<QuizFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['section_id'];

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return HasMany<QuizQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('order');
    }

    /**
     * @return HasMany<QuizAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }
}
